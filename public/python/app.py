import cv2
import face_recognition
import numpy as np
import mysql.connector
from flask import Flask, request, jsonify, send_file
from datetime import datetime, timedelta
# from datetime import datetime
import io
import csv

from reportlab.lib.pagesizes import letter
from reportlab.pdfgen import canvas
# from flask import

app = Flask(__name__)

# ---------------- Database Connection ----------------
def get_db():
    return mysql.connector.connect(
        host="192.168.0.246",      # change if remote
        user="CodeFlux",           # your MySQL username
        password="Root@123", # your MySQL password
        database="CodeFlux"
        # host="localhost",      # change if remote
        # user="root",           # your MySQL username
        # password="Root@123", # your MySQL password
        # database="face_attendance"
    )


# ---------------- Database Functions ----------------
def save_user(name, encoding):
    conn = get_db()
    c = conn.cursor()
    encoding_bytes = encoding.tobytes()
    c.execute("INSERT INTO users (name, encoding) VALUES (%s, %s) ON DUPLICATE KEY UPDATE encoding=%s",
              (name, encoding_bytes, encoding_bytes))
    conn.commit()
    conn.close()


def get_all_users():
    conn = get_db()
    c = conn.cursor()
    c.execute("SELECT id, name, encoding FROM users")
    rows = c.fetchall()
    conn.close()

    users = []
    for row in rows:
        users.append({
            "id": row[0],
            "name": row[1],
            "encoding": np.frombuffer(row[2], dtype=np.float64)
        })
    return users


def toggle_attendance(user_id):
    conn = get_db()
    c = conn.cursor()

    today = datetime.now().strftime("%Y-%m-%d")
    c.execute("SELECT status FROM attendance WHERE user_id=%s AND DATE(timestamp)=%s ORDER BY id DESC LIMIT 1",
              (user_id, today))
    row = c.fetchone()

    new_status = "IN" if not row or row[0] == "OUT" else "OUT"

    c.execute("INSERT INTO attendance (user_id, status, timestamp) VALUES (%s, %s, %s)",
              (user_id, new_status, datetime.now()))
    conn.commit()
    conn.close()

    return new_status


@app.route("/register", methods=["POST"])
def register():
    name = request.form.get("name")
    file = request.files.get("image")

    if not name or not file:
        return jsonify({"error": "Name and image are required"}), 400

    # Connect to DB
    conn = get_db()
    c = conn.cursor()

    # Check if name already exists
    c.execute("SELECT id FROM users WHERE name=%s", (name,))
    if c.fetchone():
        conn.close()
        return jsonify({"error": f"User with name '{name}' already exists"}), 400

    # Load image and get face encoding
    image = face_recognition.load_image_file(file)
    encodings = face_recognition.face_encodings(image)

    if len(encodings) == 0:
        conn.close()
        return jsonify({"error": "No face detected in the image"}), 400

    encoding = encodings[0]

    # ---------------- Duplicate Face Validation ----------------
    # Fetch all existing user encodings
    c.execute("SELECT name, encoding FROM users")
    rows = c.fetchall()
    for row in rows:
        existing_name = row[0]
        existing_encoding = np.frombuffer(row[1], dtype=np.float64)
        matches = face_recognition.compare_faces([existing_encoding], encoding)
        if matches[0]:
            conn.close()
            return jsonify({"error": f"Face already registered for user '{existing_name}'"}), 400

    # Insert new user
    encoding_bytes = encoding.tobytes()
    c.execute("INSERT INTO users (name, encoding) VALUES (%s, %s)", (name, encoding_bytes))
    conn.commit()
    conn.close()

    return jsonify({"message": f"User {name} registered successfully"})



@app.route("/attendance", methods=["POST"])
def attendance():
    file = request.files.get("image")
    if not file:
        return jsonify({"error": "Image required"}), 400

    image = face_recognition.load_image_file(file)
    encodings = face_recognition.face_encodings(image)

    if len(encodings) == 0:
        return jsonify({"error": "No face detected"}), 400

    face_encoding = encodings[0]
    users = get_all_users()

    # Compare with known faces
    for user in users:
        matches = face_recognition.compare_faces([user["encoding"]], face_encoding)
        if matches[0]:
            new_status = toggle_attendance(user["id"])
            return jsonify({"name": user["name"], "status": new_status})

    return jsonify({"error": "Face not recognized"}), 404


# ---------------- Webcam Mode ----------------
def webcam_attendance():
    users = get_all_users()
    known_encodings = [u["encoding"] for u in users]
    known_names = [u["name"] for u in users]
    known_ids = [u["id"] for u in users]

    video_capture = cv2.VideoCapture(0)
    print("Press 'q' to quit webcam mode")

    while True:
        ret, frame = video_capture.read()
        if not ret:
            break

        rgb_frame = frame[:, :, ::-1]
        face_locations = face_recognition.face_locations(rgb_frame)
        face_encodings = face_recognition.face_encodings(rgb_frame, face_locations)

        for face_encoding, face_location in zip(face_encodings, face_locations):
            matches = face_recognition.compare_faces(known_encodings, face_encoding)
            name = "Unknown"

            if True in matches:
                idx = matches.index(True)
                user_id = known_ids[idx]
                name = known_names[idx]

                status = toggle_attendance(user_id)
                print(f"{name} marked {status}")

                top, right, bottom, left = face_location
                cv2.rectangle(frame, (left, top), (right, bottom), (0, 255, 0), 2)
                cv2.putText(frame, f"{name} - {status}", (left, top - 10),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)

        cv2.imshow("Face Attendance", frame)

        if cv2.waitKey(1) & 0xFF == ord("q"):
            break

    video_capture.release()
    cv2.destroyAllWindows()


@app.route("/report", methods=["GET"])
def report():
    user_ids_str = request.args.get("user_id")
    start_date = request.args.get("start_date")
    end_date = request.args.get("end_date")

    # Default to current month
    today = datetime.now()
    if not start_date or not end_date:
        start_date = today.replace(day=1).strftime("%Y-%m-%d")
        last_day = (today.replace(day=1) + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        end_date = last_day.strftime("%Y-%m-%d")

    conn = get_db()
    c = conn.cursor(dictionary=True)

    query = """
        SELECT u.id AS user_id, u.name, a.status, a.timestamp
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE DATE(a.timestamp) BETWEEN %s AND %s
    """
    params = [start_date, end_date]

    if user_ids_str:
        # Parse comma-separated user IDs and sanitize
        user_ids = [uid.strip() for uid in user_ids_str.split(",") if uid.strip().isdigit()]
        if user_ids:
            # Prepare placeholders for SQL IN clause
            placeholders = ",".join(["%s"] * len(user_ids))
            query += f" AND u.id IN ({placeholders})"
            params.extend(user_ids)

    query += " ORDER BY u.id, a.timestamp ASC"
    c.execute(query, params)
    rows = c.fetchall()
    conn.close()

    # Process report as before...
    report_data = {}
    for row in rows:
        uid = row["user_id"]
        name = row["name"]
        date = row["timestamp"].strftime("%Y-%m-%d")
        time = row["timestamp"].strftime("%H:%M:%S")
        status = row["status"]

        if uid not in report_data:
            report_data[uid] = {"name": name, "dates": {}}

        if date not in report_data[uid]["dates"]:
            report_data[uid]["dates"][date] = {"entries": [], "total_hours": 0}

        report_data[uid]["dates"][date]["entries"].append({"status": status, "time": time})

    # Calculate total hours per day
    for uid, udata in report_data.items():
        for date, ddata in udata["dates"].items():
            entries = ddata["entries"]
            total_seconds = 0
            in_time = None

            for entry in entries:
                t = datetime.strptime(entry["time"], "%H:%M:%S")
                if entry["status"] == "IN":
                    in_time = t
                elif entry["status"] == "OUT" and in_time:
                    diff = (t - in_time).total_seconds()
                    if diff > 0:
                        total_seconds += diff
                    in_time = None

            ddata["total_hours"] = round(total_seconds / 3600, 2)  # hours

    return jsonify(report_data)







@app.route("/report/pdf", methods=["GET"])
def report_pdf():
    user_id = request.args.get("user_id")
    start_date = request.args.get("start_date")
    end_date = request.args.get("end_date")

    # Default to current month
    today = datetime.now()
    if not start_date or not end_date:
        start_date = today.replace(day=1).strftime("%Y-%m-%d")
        last_day = (today.replace(day=1) + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        end_date = last_day.strftime("%Y-%m-%d")

    conn = get_db()
    c = conn.cursor(dictionary=True)

    query = """
        SELECT u.id AS user_id, u.name, a.status, a.timestamp
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE DATE(a.timestamp) BETWEEN %s AND %s
    """
    params = [start_date, end_date]

    if user_id:
        # Parse comma-separated user IDs and sanitize
        user_ids = [uid.strip() for uid in user_id.split(",") if uid.strip().isdigit()]
        if user_ids:
            # Prepare placeholders for SQL IN clause
            placeholders = ",".join(["%s"] * len(user_ids))
            query += f" AND u.id IN ({placeholders})"
            params.extend(user_ids)

    # if user_id:
    #     query += " AND u.id = %s"
    #     params.append(user_id)

    query += " ORDER BY u.id, a.timestamp ASC"
    c.execute(query, params)
    rows = c.fetchall()
    conn.close()

    # Process report to calculate total hours per day
    report_data = {}
    for row in rows:
        uid = row["user_id"]
        name = row["name"]
        date = row["timestamp"].strftime("%Y-%m-%d")
        status = row["status"]
        time = row["timestamp"].strftime("%H:%M:%S")

        if uid not in report_data:
            report_data[uid] = {"name": name, "dates": {}}

        if date not in report_data[uid]["dates"]:
            report_data[uid]["dates"][date] = {"entries": [], "total_hours": 0}

        report_data[uid]["dates"][date]["entries"].append({"status": status, "time": time})

    # Calculate total hours per day
    for uid, udata in report_data.items():
        for date, ddata in udata["dates"].items():
            entries = ddata["entries"]
            total_seconds = 0
            in_time = None
            for entry in entries:
                t = datetime.strptime(entry["time"], "%H:%M:%S")
                if entry["status"] == "IN":
                    in_time = t
                elif entry["status"] == "OUT" and in_time:
                    diff = (t - in_time).total_seconds()
                    if diff > 0:
                        total_seconds += diff
                    in_time = None
            ddata["total_hours"] = round(total_seconds / 3600, 2)

    # ---------------- Generate PDF ----------------
    pdf_buffer = io.BytesIO()
    pdf = canvas.Canvas(pdf_buffer, pagesize=letter)
    width, height = letter

    pdf.setTitle("Attendance Report")
    pdf.setFont("Helvetica-Bold", 14)
    pdf.drawString(50, height - 50, f"Attendance Report: {start_date} to {end_date}")

    pdf.setFont("Helvetica-Bold", 12)
    y = height - 80
    pdf.drawString(50, y, "ID")
    pdf.drawString(100, y, "Name")
    pdf.drawString(250, y, "Date")
    pdf.drawString(400, y, "Total Hours")
    y -= 20

    pdf.setFont("Helvetica", 10)
    for uid, udata in report_data.items():
        name = udata["name"]
        for date, ddata in udata["dates"].items():
            if y < 50:  # new page
                pdf.showPage()
                y = height - 50
            pdf.drawString(50, y, str(uid))
            pdf.drawString(100, y, name)
            pdf.drawString(250, y, date)
            pdf.drawString(400, y, str(ddata["total_hours"]))
            y -= 20

    pdf.save()
    pdf_buffer.seek(0)

    return send_file(
        pdf_buffer,
        mimetype='application/pdf',
        as_attachment=True,
        download_name=f"attendance_report_{start_date}_to_{end_date}.pdf"
    )


@app.route("/report/csv", methods=["GET"])
def report_csv():
    user_id = request.args.get("user_id")
    start_date = request.args.get("start_date")
    end_date = request.args.get("end_date")

    # Default to current month
    today = datetime.now()
    if not start_date or not end_date:
        start_date = today.replace(day=1).strftime("%Y-%m-%d")
        last_day = (today.replace(day=1) + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        end_date = last_day.strftime("%Y-%m-%d")

    conn = get_db()
    c = conn.cursor(dictionary=True)

    query = """
        SELECT u.id AS user_id, u.name, a.status, a.timestamp
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE DATE(a.timestamp) BETWEEN %s AND %s
    """
    params = [start_date, end_date]

    if user_id:
        query += " AND u.id = %s"
        params.append(user_id)

    query += " ORDER BY u.id, a.timestamp ASC"
    c.execute(query, params)
    rows = c.fetchall()
    conn.close()

    # Process report to calculate total hours per day
    report_data = {}
    for row in rows:
        uid = row["user_id"]
        name = row["name"]
        date = row["timestamp"].strftime("%Y-%m-%d")
        time = row["timestamp"].strftime("%H:%M:%S")
        status = row["status"]

        if uid not in report_data:
            report_data[uid] = {"name": name, "dates": {}}

        if date not in report_data[uid]["dates"]:
            report_data[uid]["dates"][date] = {"entries": [], "total_hours": 0}

        report_data[uid]["dates"][date]["entries"].append({"status": status, "time": time})

    # Calculate total hours per day
    for uid, udata in report_data.items():
        for date, ddata in udata["dates"].items():
            entries = ddata["entries"]
            total_seconds = 0
            in_time = None

            for entry in entries:
                t = datetime.strptime(entry["time"], "%H:%M:%S")
                if entry["status"] == "IN":
                    in_time = t
                elif entry["status"] == "OUT" and in_time:
                    diff = (t - in_time).total_seconds()
                    if diff > 0:
                        total_seconds += diff
                    in_time = None

            ddata["total_hours"] = round(total_seconds / 3600, 2)  # hours

    # ---------------- Generate CSV ----------------
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(['ID', 'Name', 'Date', 'Total Hours'])  # header

    for uid, udata in report_data.items():
        name = udata["name"]
        for date, ddata in udata["dates"].items():
            writer.writerow([uid, name, date, ddata["total_hours"]])

    output.seek(0)

    return send_file(
        io.BytesIO(output.getvalue().encode()),
        mimetype='text/csv',
        as_attachment=True,
        download_name=f"attendance_report_{start_date}_to_{end_date}.csv"
    )


# @app.route("/attendance/multi", methods=["POST"])
# def attendance_multi():
#     file = request.files.get("image")
#     if not file:
#         return jsonify({"error": "Image required"}), 400

#     # Load image and get face encodings
#     image = face_recognition.load_image_file(file)
#     face_locations = face_recognition.face_locations(image)
#     face_encodings = face_recognition.face_encodings(image, face_locations)

#     if len(face_encodings) == 0:
#         return jsonify({"error": "No face detected"}), 400

#     users = get_all_users()
#     response_data = []

#     conn = get_db()
#     c = conn.cursor(dictionary=True)

#     for face_encoding in face_encodings:
#         matched_user = None
#         for user in users:
#             matches = face_recognition.compare_faces([user["encoding"]], face_encoding)
#             if matches[0]:
#                 matched_user = user
#                 break

#         if matched_user:
#             # Get last login/out timestamp
#             c.execute(
#                 "SELECT status, timestamp FROM attendance WHERE user_id=%s ORDER BY id DESC LIMIT 1",
#                 (matched_user["id"],)
#             )
#             last_attendance = c.fetchone()
#             last_status = last_attendance["status"] if last_attendance else None
#             last_time = last_attendance["timestamp"].strftime("%Y-%m-%d %H:%M:%S") if last_attendance else None

#             response_data.append({
#                 "id": matched_user["id"],
#                 "name": matched_user["name"],
#                 "last_status": last_status,
#                 "last_timestamp": last_time
#             })
#         else:
#             response_data.append({
#                 "id": None,
#                 "name": "Unknown",
#                 "last_status": None,
#                 "last_timestamp": None
#             })

#     conn.close()
#     return jsonify(response_data)


@app.route("/attendance/multi", methods=["POST"])
def attendance_multi():
    files = request.files.getlist("images")  # Accept multiple files
    if not files or len(files) == 0:
        return jsonify({"error": "At least one image is required"}), 400

    users = get_all_users()
    response_data = []
    matched_users_set = set()

    conn = get_db()
    c = conn.cursor(dictionary=True)

    for file in files:
        # Load image and get face encodings
        image = face_recognition.load_image_file(file)
        face_locations = face_recognition.face_locations(image)
        face_encodings = face_recognition.face_encodings(image, face_locations)

        if len(face_encodings) == 0:
            continue  # Skip this image if no face

        for face_encoding in face_encodings:
            matched_user = None
            for user in users:
                matches = face_recognition.compare_faces([user["encoding"]], face_encoding)
                if matches[0]:
                    matched_user = user
                    break

            if matched_user:
                matched_users_set.add(matched_user["name"])  # track matched names

                # Get last login/out timestamp
                c.execute(
                    "SELECT status, timestamp FROM attendance WHERE user_id=%s ORDER BY id DESC LIMIT 1",
                    (matched_user["id"],)
                )
                last_attendance = c.fetchone()
                last_status = last_attendance["status"] if last_attendance else None
                last_time = last_attendance["timestamp"].strftime("%Y-%m-%d %H:%M:%S") if last_attendance else None

                response_data.append({
                    "id": matched_user["id"],
                    "name": matched_user["name"],
                    "last_status": last_status,
                    "last_timestamp": last_time
                })

    conn.close()

    if len(response_data) == 0:
        return jsonify({"message": "No match found"}), 404

    return jsonify({
        "message": f"{len(matched_users_set)} user(s) matched",
        "matched_users": list(matched_users_set),
        "details": response_data
    })


if __name__ == "__main__":
    app.run(debug=True)
    # For webcam mode, run separately:
    # webcam_attendance()
