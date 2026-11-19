
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
<?php echo $fontCss; ?>

*{box-sizing:border-box;margin:0;padding:0}
html,body{width:<?php echo e($width); ?>px;height:<?php echo e($height); ?>px;overflow:hidden}
body{background:<?php echo e($colors['background']); ?>;color:<?php echo e($colors['text']); ?>;font-family:'<?php echo e($bodyFont); ?>',system-ui,sans-serif;position:relative}
.h{font-family:'<?php echo e($headingFont); ?>',Georgia,serif;font-weight:700;line-height:1.08;letter-spacing:-.01em;overflow-wrap:break-word}
.logo{position:absolute;max-width:<?php echo e((int) round($width * 0.16)); ?>px;max-height:<?php echo e((int) round($width * 0.1)); ?>px;object-fit:contain}
.logo.top_left{top:56px;left:56px}.logo.top_right{top:56px;right:56px}.logo.bottom_left{bottom:56px;left:56px}
.logo.bottom_right{bottom:56px;right:56px}.logo.bottom_center{bottom:56px;left:50%;transform:translateX(-50%)}
.cta{display:inline-block;background:<?php echo e($colors['primary']); ?>;color:<?php echo e($colors['background']); ?>;font-weight:700;border-radius:999px;padding:22px 44px;font-size:<?php echo e((int) round($width * 0.034)); ?>px}
.page{position:absolute;top:56px;right:56px;font-size:28px;opacity:.7}
</style>
</head>
<body>
<?php echo $__env->yieldContent('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logo): ?><img class="logo <?php echo e($logoPlacement); ?>" src="<?php echo e($logo); ?>" alt=""><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($page): ?><div class="page"><?php echo e($page); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/render/_base.blade.php ENDPATH**/ ?>