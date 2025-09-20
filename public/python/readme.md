
# Face Recognition attendance system

## Reqirement
python3 

Install step for python 
```
sudo apt update
sudo apt install python3 python3-pip -y

```

Install step for mysql 
```
pip install mysql-connector-python
```


```
pip install cmake
pip install opencv-python
pip install face_recognition
```


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    encoding BLOB
);CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    status ENUM('IN','OUT'),
    timestamp DATETIME,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
