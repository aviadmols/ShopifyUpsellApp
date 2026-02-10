<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Upsell Admin</title>
    @vite(['resources/css/app.css', 'resources/js/admin.jsx'])
</head>
<body>
    <div id="admin-root"></div>
</body>
</html>
