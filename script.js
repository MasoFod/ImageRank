// script.js - 前端逻辑: 获取对决、投票、排行榜、模态

document.addEventListener('DOMContentLoaded', () => {
    const img1 = document.getElementById('img1');
    const img2 = document.getElementById('img2');
    const slot1 = document.getElementById('slot1');
    const slot2 = document.getElementById('slot2');
    const leaderboard = document.getElementById('leaderboard');
    const modal = document.getElementById('modal');
    const modalImg = document.getElementById('modalImg');
    const modalBg = document.getElementById('modalBg');
    
    // 投票成功模态元素
    const successModal = document.getElementById('successModal');
    const closeSuccessModalBtn = document.getElementById('closeSuccessModal');

    let currentPair = [];

    function thumbUrl(path, width) {
        // 限制宽度避免请求过大图产生内存问题
        if (width > 800) width = 800;
        return 'thumb.php?src=' + encodeURIComponent(path) + '&w=' + width;
    }

    function loadPair() {
        fetch('api.php?action=pair')
            .then(r => r.json())
            .then(data => {
                if (data.length === 2) {
                    currentPair = data;
                    // 使用缩略图显示
                    setImage(img1, thumbUrl(data[0].path, 400));
                    setImage(img2, thumbUrl(data[1].path, 400));
                    img1.dataset.id = data[0].id;
                    img2.dataset.id = data[1].id;
                    img1.dataset.orig = data[0].path;
                    img2.dataset.orig = data[1].path;
                } else {
                    img1.src = '';
                    img2.src = '';
                    alert('图片数量不足，请添加至少两张图片。');
                }
            });
    }

    function setImage(imgEl, src) {
        imgEl.classList.remove('loaded');
        imgEl.src = src;
        imgEl.onload = () => imgEl.classList.add('loaded');
    }

    function vote(winnerId, loserId) {
        const form = new FormData();
        form.append('winner', winnerId);
        form.append('loser', loserId);
        fetch('api.php?action=vote', { method: 'POST', body: form })
            .then(r => r.json().then(res => ({status: r.status, body: res})))
            .then(({status, body}) => {
                if (status === 200 && body.success) {
                    // 显示投票成功模态
                    successModal.classList.remove('hidden');
                    // 稍微淡化当前图片
                    slot1.style.opacity = 0.5;
                    slot2.style.opacity = 0.5;
                } else {
                    alert(body.error || '投票失败');
                }
            })
            .catch(() => alert('网络错误'));
    }

    // 点击图片打开大图模态（使用原始路径）
    [img1, img2].forEach(img => {
        img.addEventListener('click', () => {
            modalImg.src = img.dataset.orig || img.src;
            modal.classList.remove('hidden');
        });
    });

    // 投票按钮
    document.getElementById('vote1').addEventListener('click', (e) => {
        e.stopPropagation();
        vote(img1.dataset.id, img2.dataset.id);
    });
    document.getElementById('vote2').addEventListener('click', (e) => {
        e.stopPropagation();
        vote(img2.dataset.id, img1.dataset.id);
    });

    modalBg.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // 关闭投票成功模态并加载下一组
    closeSuccessModalBtn.addEventListener('click', () => {
        successModal.classList.add('hidden');
        slot1.style.opacity = 1;
        slot2.style.opacity = 1;
        loadPair();
    });

    // 自动加载排行榜
    function loadLeaderboard() {
        fetch('api.php?action=leaderboard')
            .then(r => r.json())
            .then(rows => {
                leaderboard.innerHTML = rows.map(r =>
                    `<div class="lb-item" data-orig="${r.path}">` +
                    `<img src="${thumbUrl(r.path,200)}" alt="">` +
                    `</div>`
                ).join('');
                // 点击缩略图打开模态
                Array.from(leaderboard.querySelectorAll('.lb-item')).forEach(el => {
                    el.addEventListener('click', () => {
                        modalImg.src = el.dataset.orig;
                        modal.classList.remove('hidden');
                    });
                });
            });
    }

    loadLeaderboard();

    // 绑定刷新按钮
    document.getElementById('refreshLb').addEventListener('click', () => {
        loadLeaderboard();
    });

    // 换一组按钮
    document.getElementById('shuffleBtn').addEventListener('click', () => {
        loadPair();
    });

    loadPair();
});
