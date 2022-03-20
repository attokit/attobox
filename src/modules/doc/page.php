<?php
$doc = $_Params["doc"];
$doc_instance = $doc["instance"];
$doc_content = $doc["content"];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<link rel="stylesheet" href="/src/module/doc/css/github-markdown-light.css" />
</head>
<body>

    <div class="markdown-body">
        <?php echo $doc_content; ?>
    </div>
    
</body>
</html>