# Project Development Guidelines

This document provides essential information for developers working on the Eremo Frate Francesco project.

## Build/Configuration Instructions

### Database Setup

1. Create a MySQL/MariaDB database named `my_eremofratefrancesco`
2. Create a database user `eremofratefrancesco` with appropriate permissions
3. Import the database schema from `eremo/database/QueryCreazioneTabelle.txt`
4. Update the database password in `eremo/src/public/api/db_config.php`

```php
// In db_config.php
$host = "localhost";
$user = "eremofratefrancesco";
$db_password = "your_secure_password"; // Update this
$dbname = "my_eremofratefrancesco";
```

### Project Setup

1. Clone the repository
2. Ensure you have PHP 7.4+ installed with mysqli extension enabled
3. Configure a web server (Apache/Nginx) to serve the `eremo/src/public` directory
4. Ensure the web server has write permissions for media uploads

## Testing Information

### Database Connection Test

Create a test file to verify database connectivity:

```php
<?php
// db_connection_test.php
require_once 'path/to/eremo/src/public/api/db_config.php';

echo "Database connection test: ";
if ($conn && $conn->ping()) {
    echo "SUCCESS - Connected to database '{$dbname}' as user '{$user}'";
} else {
    echo "FAILED - Could not connect to database";
}

$conn->close();
echo "\nTest completed.";
?>
```

Run the test with:
```
php db_connection_test.php
```

### API Testing

For testing API endpoints:

1. Create a test script that sends requests to the API endpoints
2. Verify the response format and status codes
3. Test with both valid and invalid inputs

Example API test:

```php
<?php
// api_test.php
session_start();
$_SESSION['user_email'] = 'test@example.com'; // Simulate logged-in user

// Test endpoint
$api_url = 'http://localhost/path/to/eremo/src/public/api/api_get_user_profile.php';
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "API Test Result:\n";
echo "Status Code: $status_code\n";
echo "Response: $response\n";
?>
```

### Adding New Tests

When adding new tests:
1. Create a dedicated test file for each component or feature
2. Follow the existing pattern of setting up test data, executing the test, and verifying results
3. Include both positive and negative test cases
4. Clean up any test data after the test completes

## Development Guidelines

### Code Style

1. Use prepared statements for all database queries
2. Follow consistent error handling with HTTP status codes and JSON responses
3. Structure JSON responses with a 'success' flag and data/message fields:
   ```json
   {
     "success": true,
     "data": { ... }
   }
   ```
   or
   ```json
   {
     "success": false,
     "message": "Error description"
   }
   ```
4. Close database connections and statements after use
5. Use session management for authentication
6. Include comments explaining complex logic
7. Italian language is used for user-facing messages

### Security Practices

1. Always validate and sanitize user input
2. Use prepared statements to prevent SQL injection
3. Implement proper session management
4. Set appropriate HTTP headers for API responses
5. Implement proper access control checks in each endpoint

### Project Structure

- `eremo/src/public/` - Web-accessible files
- `eremo/src/public/api/` - API endpoints
- `eremo/database/` - Database schema and related files
- `eremo/src/images/` - Static image assets

### Debugging

1. Check PHP error logs for backend issues
2. Use browser developer tools for frontend debugging
3. For API issues, verify the request/response using browser network tools or tools like Postman
4. Database issues can be debugged by examining the SQL queries directly in the database management tool