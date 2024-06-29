# attobox
Simple PHP framework for developing usage. Provide http request &amp; response processing, router, resource building, and basic ORM supporting.

## Usage
Use composer

```shell
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

```
# ./.htaccess

<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase /

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
</IfModule>
```

```index.php``` is the web entrance. In this file, you need to require ```start.php``` of attobox framework.

```php
# ./index.php

require_once(__DIR__."/vendor/attokit/attobox/src/start.php");
Box::start([
    //all init config goes here
    "WEB_PROTOCOL"  => "http",
    "WEB_DOMAIN"    => "localhost",
    "WEB_IP"        => "127.0.0.1",
    "WEB_DOMAIN_AJAXALLOWED" => "localhost",
    //...
]);
```

```/route/Web.php``` is required. All your custom controllers must be defined as a public method of Class ```\Atto\Box\route\Web``` in this file. 

```php
# ./route/Web.php

namespace Atto\Box\route;

class Web extends Base
{
    /**
     * this will define a controller named index
     * @param Array $args URI array
     * if request url == https://your.domain/index/foo/bar
     * then $args = ["foo", "bar"]
     */
    public function index(...$args)
    {
        $rtn = [
            "hello" => "world"
        ];

        //default response type is html
        return "string";    //echo "string"
        return $rtn;        //echo "{'hello': 'world'}"

        //you can assign response type by using query string

        //...?format=json
        return "string";    //{error:false, errors:[], data:'string'}
        return $rtn;        //{error:false, errors:[], data: {hello: 'world'}}
        //or
        Response::json($rtn);

        //...?format=dump
        return $rtn;        //var_dump($rtn)
        //or
        Response::dump($rtn);

        //...?format=str
        return $rtn;        //echo "{hello:'world'}"
        //or
        Response::str($rtn);

        /**
         * !!! Use return method
         * !!! Response::[method] is NOT Recommand
         */

        //you can response a PHP page like using view
        //page file recommand in /page, but you can put it anywhere
        $page = path_find("page/someView.php");
        Response::page($page, [
            //you can access these params in view page
            "rtn" => $rtn,
            //...
        ]);

        //you can response a code
        Response::code(500);

        //other response usage such as headers, you can check the Response Class in vendor/attokit/attobox/src/Response.php
    }
}
```

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

```php
# ./app/appname/Appname.php

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
```

### /assets
All static resources should be stored here. Such as images, js, css, etc. You can makeup whatever folders you like. 

The default resource route is ```src```, you can request any resource that stored in assets folder. If you have a image file ```/assets/image/img01.jpg```, then you can request it like ```https://your.domain/src/image/img01.jpg```. You also can adjust the image a little bit by using query string like ```https://your.domain/src/image/img01.jpg?thumb=128,128```.

You can checkout all Mimes that supported by attobox in ```vendor/attokit/attobox/src/modules/resource```.

### /library
You can create your own Class here. Require namespace ```Atto\Box```. If this folder is in ```/app/appname```, namespace should be ```Atto\Box\App\appname```.

### /page
Simple PHP page can export directly. Can request like ```https://your.domain/page/pagename```, if in app folder request like ```https://your.domain/appname/page/pagename```.

### /record
See [ORM Support](#ORM-Support).

### /route
Custom route Class file. Public method can be request as controller. 

Special route file ```Web.php``` ```Dbm.php``` ```Src.php``` ```Uac.php```, cannot use these file names.

## ORM Support
Attobox provide simple ORM support. Only for personal dev usage, this framework recommand using sqlite3 for database actions.

Sqlite file must stored in ```/library/db``` or ```/app/appname/library/db```, for advanced usage, you also need to create some config params in ```[/app/appname]/library/db/config/dbname.json```, the sample of this config json file can be found in ```vendor/attokit/attobox/src/db/config_sample.json```.

Record Class must create in ```[/app/appname]/record/dbname/Tablename.php```, must extends from ```Atto\Box\Record```. RecordSet Class must defined in same php file with Record Class. Record Object is based on table row, RecordSet contains Record Objects, and it can be used as a iterator, each item is a Record Object, result would be an indexed array.

Record Class file like :

```php
# ./record/usr/Usr.php

namespace Atto\Box\record;

use Atto\Box\Record;
use Atto\Box\RecordSet;

use Atto\Box\Counter;

class Usr extends Record
{
    //generator method for auto increment ids
    //this method only triggered before insert
    public function __generateUid()
    {
        //create uid
        //use Counter in vendor/attokit/attobox/src/modules/Counter
        $uidx = Counter::auto("usr_usr_uid");
        $uidx = str_pad($uidx, 4, "0", STR_PAD_LEFT);
        $uid = "U".$uidx;
        return $uid;
    }

    //automatically process before/after insert/update/delete
    protected function beforeInsert() {return $this;}
    protected function afterInsert($result) {return $this;}
    protected function beforeUpdate() {return $this;}
    protected function afterUpdate($result) {return $this;}
    
}

class UsrSet extends RecordSet
{
    //custom methods
    public function disabled()
    {
        //disabled all usrs in usrset
        $this->setField("enable", 0);
        $this->save();
        return $this;
    }
}
```

### CURD
Use [catfan/Medoo](https://github.com/catfan/medoo) as db driver. 

Common CURD method samples like :

```php
use Atto\Box\route\Base;
use Atto\Box\Db;
use Atto\Box\db\Table;
use Atto\Box\record\Usr;

class SomeRoute extends Base
{
    //method
    public function dbtest()
    {
        $usrdb = Db::load("sqlite:usr");
        $usrtb = $usrdb->table("usr");
        //or
        $usrtb = Table::load("usr/usr");

        //get recordset
        $usrs = $usrtb->whereEnable(1)->whereName("~","jack")->limit(10)->select();
        //or
        $usrs = $usrtb->query->apply([
            "where" => [
                "enable" => 1,
                "name[~]" => "jack",    //LIKE '%jack%'
            ],
            "limit" => 10
        ])->select();

        //get record
        $usr = $usrtb->whereUid("123")->single();

        //read record field value as property
        $uname = $usr->context["name"];
        //or
        $uname = $usr->name;
        $unames = $usrset->name;    //[uname, uname, ...]
        $extra = $usr->extra;       //json to array

        //iterate recordset
        for ($i=0;$i<count($usrs);$i++) {
            //...
        }
        //or
        $rst = [];
        $usrs->each(function($usr) use (&$rst){
            //...
        });

        //export record object to associate array
        $usrinfo = $usr->export(
            "show",     //export type: ctx, db, form, show, table
            true,       //auto calc virtual field, defined in config.json
            true,       //auto query related table record, defined in config.json
        );

        //edit record
        $usr->setField("fieldname", "value");
        $usr->save();
        //edit recordset multiple edit records
        $usrs->setField([
            "field1" => "val1",
            "field2" => "val2",
            "field3" => [
                "jsonkey" => "jsonval"
            ]
        ], true);   //if contains json field, need be true
        $usrs->save();

        //insert new record
        $newusr = $usrtb->new([
            //init record data
        ]);
        $newusr->setField([
            //record data
        ]);
        $newusr->save();

        //delete, will automatically trigger before/afterDelete()
        $newusr->del();
    }
}
```




    






