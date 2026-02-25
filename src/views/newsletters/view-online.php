<!DOCTYPE html>
<html>
<head>
    <title>{{ $newsletter->subject }}</title>

    <style>
        body {
            margin: 0;
            padding: 40px;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        .newsletter-container {
            max-width: 680px;
            margin: auto;
            background: white;
            padding: 30px;
        }

        img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>

<body>

<div style="text-align:center; padding:12px; font-size:13px; color:#666;">
    Having trouble reading this?
    <a href="/newsletter" style="color:#007bff;">
        View this newsletter online
    </a>
</div>

<div class="newsletter-container">
    {!! $html !!}
</div>

</body>
</html>