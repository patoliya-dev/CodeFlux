<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Face Recognition Attendance System')</title>

    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Modern Header */
        .header-bar {
            background: linear-gradient(90deg, #667eea, #764ba2);
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .header-bar .navbar-brand {
            font-size: 1.6rem;
            font-weight: bold;
            color: #fff;
        }
        .header-bar .navbar-brand i {
            margin-right: 8px;
        }
        .header-bar .nav-link {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            margin-left: 15px;
        }
        .header-bar .nav-link:hover {
            color: #fff;
        }

        /* Container for Attendance Form */
        .attendance-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px 15px;
        }

        .attendance-card {
            background: #fff;
            border-radius: 15px;
            padding: 30px 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .attendance-card h2 {
            font-weight: bold;
            margin-bottom: 25px;
            color: #4b0082;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #764ba2;
        }

        .btn-gradient {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: #fff;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-gradient:hover {
            background: linear-gradient(to right, #764ba2, #667eea);
        }

        footer {
            background: #2c2c54;
            color: #fff;
            text-align: center;
            padding: 15px 0;
        }

        #previewImage {
            margin-top: 15px;
            border-radius: 10px;
            max-width: 100%;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Modern Header Bar -->
    <nav class="navbar header-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="{{ url('/') }}">
                <i class="bi bi-person-check-fill"></i> Face Attendance
            </a>
            <div class="d-none d-md-flex">
                <a class="nav-link" href="{{ route('attendance')}}">Attandance</a>
                <a class="nav-link" href="{{ route('attendance.report') }}">Report</a>
                <a class="nav-link" href="{{ route('attendance.multiple') }}">Multiple Recognition</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="attendance-container">
        @yield('content')
    </div>

    <!-- Footer -->
    <footer>
        &copy; {{ date('Y') }} Face Recognition Attendance System. All rights reserved.
    </footer>


    <!-- Bootstrap JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
