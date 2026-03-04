<!DOCTYPE html>
<html>
<head>
    <title>API Docs</title>

    <link rel="stylesheet"
          href="https://unpkg.com/swagger-ui-dist/swagger-ui.css"/>

    <style>
        body {
            margin: 0;
        }
    </style>
</head>
<body>

<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
    window.onload = () => {
        SwaggerUIBundle({
            url: "/openapi.json",
            dom_id: "#swagger-ui",
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
            ],
            layout: "BaseLayout"
        });
    };
</script>

</body>
</html>