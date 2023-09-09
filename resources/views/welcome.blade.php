<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;600&display=swap" rel="stylesheet">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
        <script>
            function testHere() {
                var t = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NmU5NmZmOC1jMDk4LTQ1MzktODQ5ZC00MzIwODZjN2M2ODAiLCJqdGkiOiIwZDFiMzU4ZDA2ZWZiMWJmNmNhNzA3ZGZmNmZkMjNjZTAxYWRmODliZGJhOTgyZjE0M2ZjYjU0NmFjN2Q4ZGZhMmU1MmI2YzI3NjdkZDlmOSIsImlhdCI6MTY4Njc5MDUyNiwibmJmIjoxNjg2NzkwNTI2LCJleHAiOjE3MTg0MTI5MjYsInN1YiI6IjIwMSIsInNjb3BlcyI6W119.OMlsrV0tcrqxJySVw0FA1wh9vaYwAXc-p0b5MOsOdZmNh2jTJZsCZdmORdtPL4SUbRVVxT53sEHWKcIJWldqLFuNFEO8YXDXlZL59AaqiOkYZxFRyika8QuBwgIZr8EvpRL0WBBY4PKRaHyFl8p3EQCsfO6xjtKenwDDWGY9Ly1WmYKrU5VoCj4OxTob_7cfndvWD2q-uut7wAEeYkkCSCbb1OsbmUqHFkrkYKr38u_quOS40-TOiz-6jzksnupQvC-l88ofkCBK5qnTzP-0j-kdFOaiNlmTaj8pkbj7JfNDioyG_T8okq2oVsNrl3Rf_vSmeaXN2eHGgbmR0pXXOluV_0fjNKYMH1fMabuy05ZZVM7OUXacP4mmNIZRvdW-4IaD-fevT5iwg_isLHam09H-89dou73L0iWhR8Pyc6BfP2Uk1ZV8IL9Bfj0RUITYul61JfG_eH_zf9ilQN2PYT7vsiRe38hvnwpwxqoezYcxQPAbtbQ4iqRQc4Iwd97dY4e0Au57FpmJiT9N7n6KEyYNBTG3B4mnjI8ASJlu5hwWpZNyZZRIPxoyCCOcHNSdqN9RhUs2OtoFKrJXfWeBft8BHVsLnu4quo2levQmeHXhXAx3kMDIkR9QWnRPhMLP9iEgzcsktxyO-QGGkDm09MY6GK1PiYa-ox-c19-0_5Q';
                window.opener.postMessage(t, 'http://localhost:5173');
                window.close();
            }
        </script>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            @if (Route::has('login'))
                <div class="top-right links">
                    @auth
                        <a href="{{ url('/home') }}">Home</a>
                    @else
                        <a href="{{ route('login') }}">Login</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}">Register</a>
                        @endif
                    @endauth
                </div>
            @endif

            <div class="content">
                <div class="title m-b-md">
                    Laravel
                    <button onclick="testHere(event)" type="button">Test here</button>
                </div>

                <div class="links">
                    <a href="https://laravel.com/docs">Docs</a>
                    <a href="https://laracasts.com">Laracasts</a>
                    <a href="https://laravel-news.com">News</a>
                    <a href="https://blog.laravel.com">Blog</a>
                    <a href="https://nova.laravel.com">Nova</a>
                    <a href="https://forge.laravel.com">Forge</a>
                    <a href="https://vapor.laravel.com">Vapor</a>
                    <a href="https://github.com/laravel/laravel">GitHub</a>
                </div>
            </div>
        </div>
    </body>
</html>
