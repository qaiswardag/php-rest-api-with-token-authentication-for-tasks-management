# PHP REST API with Token Authentication for Tasks Management

This repository contains a PHP-based REST API with token authentication, designed to manage tasks. It follows a token-based authentication method to ensure secure access to the API endpoints.

## Features

- **Token-based Authentication:** Secure authentication mechanism using tokens.
- **Task Management:** Create, update, and delete tasks through API requests.
- **Error Handling:** Custom error messages and HTTP status codes for better debugging and client interaction.
- **Preflight Requests (CORS):** Handles preflight `OPTIONS` requests for cross-origin resource sharing (CORS).

## Prerequisites

- **PHP 7.2+**
- **MySQL/MariaDB** for the database
- **Composer** to manage dependencies
- A web server like Apache or Nginx

## Setup

1. Clone the repository:

    ```bash
    git clone https://github.com/your-username/php-rest-api-with-token-authentication-for-tasks-management.git
    ```

2. Install dependencies:

    ```bash
    composer install
    ```

3. Set up the database:

    Import the provided SQL file to create the necessary tables in your database.

    ```bash
    mysql -u username -p database_name < path/to/sqlfile.sql
    ```

4. Configure the database connection in `db.php`:

    ```php
    <?php
    class DB {
        private static $writeDBConnection;
        
        public static function connectWriteDB() {
            if (self::$writeDBConnection === null) {
                self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=your_database;charset=utf8', 'your_user', 'your_password');
                self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }
            return self::$writeDBConnection;
        }
    }
    ```

5. Run the API on your local server or configure it on a production server.

## Endpoints

### User Registration

- **URL:** `/register`
- **Method:** `POST`
- **Request Body:** JSON

    ```json
    {
        "fullname": "John Doe",
        "username": "john123",
        "password": "yourPassword"
    }
    ```

- **Response:**

    - Success: `201 Created`
    - Error: `400 Bad Request` | `409 Conflict` (if username exists)

```php
<?php

require_once 'db.php';
require_once '../model/response.php';

// Database connection setup and validation logic here

// Sample user registration handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawPostData = file_get_contents('php://input');
    if ($jsonData = json_decode($rawPostData)) {
        if (isset($jsonData->fullname, $jsonData->username, $jsonData->password)) {
            $fullname = trim($jsonData->fullname);
            $username = trim($jsonData->username);
            $password = password_hash($jsonData->password, PASSWORD_DEFAULT);
            
            try {
                $query = $writeDB->prepare('SELECT id FROM tblusers WHERE username = :username');
                $query->bindParam(':username', $username, PDO::PARAM_STR);
                $query->execute();
                
                if ($query->rowCount() > 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(409);
                    $response->setSuccess(false);
                    $response->addMessage('Username already exists');
                    $response->send();
                } else {
                    $query = $writeDB->prepare('INSERT INTO tblusers (fullname, username, password) VALUES (:fullname, :username, :password)');
                    $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
                    $query->bindParam(':username', $username, PDO::PARAM_STR);
                    $query->bindParam(':password', $password, PDO::PARAM_STR);
                    $query->execute();
                    
                    $response = new Response();
                    $response->setHttpStatusCode(201);
                    $response->setSuccess(true);
                    $response->addMessage('User created successfully');
                    $response->send();
                }
            } catch (PDOException $ex) {
                error_log('Database error: ' . $ex);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage('Internal server error');
                $response->send();
            }
        } else {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Incomplete user data');
            $response->send();
        }
    }
}
