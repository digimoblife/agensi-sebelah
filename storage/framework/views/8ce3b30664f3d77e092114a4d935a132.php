<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($photo): ?>
    <div style="position:absolute;inset:0;background:url('<?php echo e($photo); ?>') center/cover no-repeat"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.15) 0%,rgba(0,0,0,.7) 100%)"></div>
<?php else: ?>
    <div style="position:absolute;inset:0;background:linear-gradient(160deg,<?php echo e($colors['secondary']); ?> 0%,<?php echo e($colors['background']); ?> 100%)"></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div style="position:absolute;left:80px;right:80px;top:38%;color:<?php echo e($photo ? '#fff' : $colors['text']); ?>">
    <div class="h" style="font-size:<?php echo e((int) round($width * 0.09)); ?>px"><?php echo e($headline); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cta): ?><div style="margin-top:56px"><span class="cta"><?php echo e($cta); ?></span></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('render._base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/render/story.blade.php ENDPATH**/ ?>