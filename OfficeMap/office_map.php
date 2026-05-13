<?php
require_once __DIR__ . '/../includes/session.php';
// optional auth enforcement; uncomment to require login
// require_login();
?>
<!DOCTYPE html>
<html
    lang="en"
    class="light-style layout-menu-fixed"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="/inventory_cao_v2/assets/"
    data-template="vertical-menu-template-free">

<?php include __DIR__ . '/../includes/head.php'; ?>

<body>

<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

        <!-- SIDEBAR -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- PAGE -->
    

            <!-- NAVBAR -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

    

                <!-- CONTENT -->
            

                    <?php include __DIR__ . '/office_map_content.php'; ?>

               

                <!-- FOOTER -->
                <?php include  'footer.php'; ?>

                <div class="content-backdrop fade"></div>
            </div>
             </div>
            <!-- /CONTENT WRAPPER -->

        </div>
        <!-- /PAGE -->

    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
</div>

<!-- CORE JS -->
<script src="/inventory_cao_v2/assets/vendor/libs/jquery/jquery.js"></script>
<script src="/inventory_cao_v2/assets/vendor/libs/popper/popper.js"></script>
<script src="/inventory_cao_v2/assets/vendor/js/bootstrap.js"></script>
<script src="/inventory_cao_v2/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="/inventory_cao_v2/assets/vendor/js/menu.js"></script>
<script src="/inventory_cao_v2/assets/js/main.js"></script>

<!-- Dynamic layout adjustments: keep sidebar and content sizing responsive -->
<style>
    html, body { height: 100%; }
    .layout-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
    .layout-container { flex: 1 1 auto; display: flex; align-items: stretch; }
    .container-xxl.container-p-y { flex: 1 1 auto; display: flex; flex-direction: column; }
    .layout-menu { height: auto; }
    .layout-overlay { z-index: 50; }
    @media (max-width: 767px) {
        .layout-container { padding-left: 0; }
    }
</style>

<script>
// Adjust layout heights so sidebar and main content fill viewport properly
(function(){
    function adjustLayout(){
        try{
            var wrapper = document.querySelector('.layout-wrapper');
            var container = document.querySelector('.layout-container');
            var main = document.querySelector('.container-xxl.container-p-y');
            var nav = document.querySelector('header, .layout-navbar, nav');
            var footer = document.querySelector('footer, .footer');

            var winH = window.innerHeight || document.documentElement.clientHeight;
            var navH = nav ? nav.getBoundingClientRect().height : 0;
            var footerH = footer ? footer.getBoundingClientRect().height : 0;
            var avail = Math.max(200, winH - navH - footerH - 24); // keep some padding

            if(wrapper) wrapper.style.minHeight = winH + 'px';
            if(container) container.style.minHeight = (avail + navH) + 'px';
            if(main) main.style.minHeight = avail + 'px';
        }catch(e){ console && console.warn && console.warn('adjustLayout error', e); }
    }

    window.addEventListener('resize', adjustLayout);
    document.addEventListener('DOMContentLoaded', function(){
        adjustLayout();
        // observe DOM changes (menu toggle) to re-adjust
        var target = document.querySelector('.layout-container');
        if(window.MutationObserver && target){
            var mo = new MutationObserver(function(){ setTimeout(adjustLayout, 60); });
            mo.observe(target, { attributes: true, childList: true, subtree: true });
        }
    });
})();
</script>

</body>
</html>
