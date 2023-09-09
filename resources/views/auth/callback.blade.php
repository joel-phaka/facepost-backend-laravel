<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <script>
        @if ($platform == 'spa' && !empty($spa_app_url))
            @if(!$errors->has('auth'))
                window.opener.postMessage(JSON.stringify({access_token: '{{ $access_token }}', error: null}), '{{$spa_app_url}}');
            @else
                window.opener.postMessage(JSON.stringify({error: '{{ $errors->first('auth') }}'}), '{{$spa_app_url}}');
            @endif
        @endif
        window.close();
    </script>
</body>
</html>
