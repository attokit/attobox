<?php
$doc = $_Params["doc"];
$doc_instance = $doc["instance"];
$doc_content = $doc["content"];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/normalize/8.0.1/normalize.min.css" />
    <link rel="stylesheet" href="/src/module/doc/css/github-markdown-light.css" />
    <style>
        :root {
            --color-gray-border: #f0f0f0;
        }

        #attodoc_topbar {
            position: fixed; display: flex; width: 100vw; height: 64px; overflow: hidden;
            align-items: center; justify-content: flex-start;
            background-color: #fff;
            box-sizing: border-box;
            border-style: solid; border-width: 0 0 1px 0; border-color: var(--color-gray-border);
            z-index: 100;
        }
            #attodoc_logo {
                width: 256px; height: 64px; margin: 0 0 0 25px;
            }
            #attodoc_title {
                font-family: 'Pingfang SC', 'Segoe UI', 'Microsoft Yahei', sans-serif;
                font-weight: 900;
                font-size: 28px;
            }
    </style>
</head>
<body>
    <div id="attodoc_topbar">
        <img id="attodoc_logo" src="/src/logo_doc.svg">
        <span id="attodoc_title">API</span>
    </div>
    <div class="markdown-body">
        <?php echo $doc_content; ?>
    </div>

</body>
</html>