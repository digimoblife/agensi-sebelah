<?php $__env->startSection('content'); ?>
<div style="position:absolute;top:0;left:0;width:22px;height:100%;background:<?php echo e($colors['primary']); ?>"></div>
<div style="position:absolute;left:96px;right:96px;top:50%;transform:translateY(-55%)">
    <div class="h" style="font-size:<?php echo e((int) round($width * 0.072)); ?>px;color:<?php echo e($colors['primary']); ?>"><?php echo e($headline); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($body): ?><div style="margin-top:36px;font-size:<?php echo e((int) round($width * 0.038)); ?>px;line-height:1.4"><?php echo e($body); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cta): ?><div style="margin-top:56px"><span class="cta"><?php echo e($cta); ?></span></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('render._base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/render/slide.blade.php ENDPATH**/ ?>