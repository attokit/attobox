# attobox
Simple PHP framework for developing usage. Provide http request &amp; response processing, router, resource building, and basic ORM supporting.

## Usage
Use composer

```
$ cd /your/web/root
$ composer require attokit/attobox
```

This will generate ```vendor``` folder in your website root.

These folders are required in your website root:

    /your/web/root
        /app        #different actions for your website
        /assets     #all static resources, e.g., images, js
        /library    #db, custom PHP Class files
        /page       #PHP pages need to show directly
        /record     #ORM Record Class files for each table
        /route      #routes file
            /Web.php    #basic route extended from base route

You also need these files in your website root:

    /your/web/root
        /.htaccess
        /index.php

```.htaccess``` for Apache server, cause all http requests will go through ```index.php```, so ...

    ./.htaccess

    <IfModule mod_rewrite.c>
        RewriteEngine on
        RewriteBase /

        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
    </IfModule>

```index.php``` is the web entrance. In this file, you need to require ```start.php``` of attobox framework.

    ./index.php

    require_once(__DIR__."/vendor/attokit/attobox/src/start.php");
    Box::start([
        //all init config goes here
        "WEB_PROTOCOL"  => "http",
        "WEB_DOMAIN"    => "localhost",
        "WEB_IP"        => "127.0.0.1",
        "WEB_DOMAIN_AJAXALLOWED" => "localhost",
        //...
    ]);

```/route/Web.php``` is required. All your custom controllers must be defined as a public method of Class ```\Atto\Box\route\Web``` in this file. 

    ./route/Web.php

    namespace Atto\Box\route;

    class Web extends Base
    {
        //this will define a controller named index
        public function index(...$args)
        {
            return [
                "hello" => "world"
            ];
        }
    }

Now you can request your website like ```https://your.domain/index```.

## Folders

### /app
Each ```/app/[appname]``` folder require a PHP Class file ```Appname.php```. Must extended from ```\Atto\Box\App```. And the directory structure of each ```/app/[appname]``` would be like:

    /app/appname
        /assets
        /library
        /page
        /record
        /Appname.php

Basicly, each app can be treated as vhost. The usage of these folders are same to the folders in the website root.

```/app/appname/Appname.php``` must extends from ```Atto\Box\App```, each public method of this Class can be requested as controller like ```https://your.domain/appname[/method]```

    ./app/appname/Appname.php

    namespace Atto\Box\App;

    use Atto\Box\App;
    
    class Appname extends App
    {
        //default route(controller)
        //https://your.domain/appname
        public function defaultRoute(...$args)
        {
            return "appname/indexController";
        }

        //custom route(controller)
        //https://your.domain/appname/foobar
        public function foobar(...$args)
        {
            return "appname/customController";
        }
    }

### /assets
All static resources should be stored here. Such as images, js, css, etc. You can makeup whatever folders you like. 

The default resource route is ```src```, you can request any resource that stored in assets folder. If you have a image file ```/assets/image/img01.jpg```, then you can request it like ```https://your.domain/src/image/img01.jpg```. You also can adjust the image a little bit by using query string like ```https://your.domain/src/image/img01.jpg?thumb=128,128```.

You can checkout all Mimes that supported by attobox in ```vendor/attokit/attobox/src/modules/resource```.

### /library
You can create your own Class here. Require namespace ```Atto\Box```. If this folder is in ```/app/appname```, namespace should be ```Atto\Box\App\appname```.


    






