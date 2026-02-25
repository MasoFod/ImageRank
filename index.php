<?php
// index.php - 主页面, 前端界面与最少PHP
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>图片对决评分</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header id="pageHeader">
    <h1>图片对决评分系统</h1>
</header>
<div id="container">
    <aside id="sidebar">
        <h2>排行榜</h2>
        <button class="refreshBtn" id="refreshLb">刷新</button>
        <div id="leaderboard"></div>
    </aside>
    <main id="main">
        <div class="controls">
            <button id="shuffleBtn">换一组</button>
        </div>
        <div class="image-slots">
            <div class="image-slot" id="slot1">
                <img id="img1" src="" alt="">
                <button class="voteBtn" id="vote1">投票</button>
            </div>
            <div class="image-slot" id="slot2">
                <img id="img2" src="" alt="">
                <button class="voteBtn" id="vote2">投票</button>
            </div>
        </div>
    </main>
</div>
<footer id="pageFooter">&copy; 2026 图片对决评分</footer>

<div id="modal" class="hidden">
    <div id="modalBg"></div>
    <img id="modalImg" src="" alt="大图">
</div>

<div id="successModal" class="hidden">
    <div id="successModalBg"></div>
    <div id="successModalContent">
        <h2>投票成功！</h2>
        <button id="closeSuccessModal">关闭</button>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
