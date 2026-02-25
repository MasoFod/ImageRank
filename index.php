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
    <h1 data-i18n="title">图片对决评分系统</h1>
    <p id="subtitle" data-i18n="subtitle">点击按钮为您喜欢的图片投票，或点击图片查看大图。</p>
    <select id="langSelect">
        <option value="zh">中文</option>
        <option value="en">English</option>
    </select>
</header>
<div id="container">
    <aside id="sidebar">
        <h2 data-i18n="leaderboard">排行榜</h2>
        <button class="refreshBtn" id="refreshLb" data-i18n="refresh">刷新</button>
        <div id="leaderboard"></div>
    </aside>
    <main id="main">
        <!-- 顶部控制区：换一组 -->
        <div class="top-controls">
            <button id="shuffleBtn" class="action-btn" data-i18n="shuffle">换一组</button>
        </div>
        
        <!-- 投票控制区：两个投票按钮 -->
        <div class="vote-controls">
            <button id="vote1" class="action-btn" data-i18n="voteLeft">投给左边</button>
            <button id="vote2" class="action-btn" data-i18n="voteRight">投给右边</button>
        </div>

        <!-- 图片展示区 -->
        <div class="image-slots">
            <div class="image-slot" id="slot1">
                <img id="img1" src="" alt="">
            </div>
            <div class="image-slot" id="slot2">
                <img id="img2" src="" alt="">
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
        <h2 data-i18n="voteSuccess">投票成功！</h2>
        <button id="closeSuccessModal" data-i18n="close">关闭</button>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
