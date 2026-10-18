<?php

$host = "localhost";
$user = "root";
$password = "";
$db_Name = "gimt_erp";

$conn = new mysqli($host, $user, $password, $db_Name);

if ($conn->connect_error) {
    echo "<!DOCTYPE html>

<head>
    
    
    <title>500 - Internal Server Error</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #fffaf5; color: #382418; display: grid; place-content: center; height: 100vh; margin: 0; text-align: center; }
        h1 { font-size: 4rem; color: #c2410c; margin: 0; }
        p { color: #735f50; margin: 0.5rem 0 1.5rem; }
        a { color: #fff; background: #c2410c; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-weight: bold; }
        a:hover { background: #9a3412; }
    </style>
</head>
<body>
    <div>
        <h1>500</h1>
        <p>Internal Server Error. Please try again later.</p>
        <a href=" / ">Go Home</a>
    </div>
</body>
</html>";
} 
