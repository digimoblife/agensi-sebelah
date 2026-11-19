<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($photo): ?>
    <div style="position:absolute;inset:0;background:url('<?php echo e($photo); ?>') center/cover no-repeat"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 35%,rgba(0,0,0,.72) 100%)"></div>
<?php else: ?>
    <div style="position:absolute;inset:0;background:<?php echo e($colors['secondary']); ?>"></div>
    <div style="position:absolute;left:-12%;bottom:-18%;width:70%;height:55%;border-radius:50%;background:<?php echo e($colors['accent'] ?? $colors['primary']); ?>;opacity:.35"></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div style="position:absolute;left:72px;right:72px;bottom:<?php echo e($logo ? 210 : 120); ?>px;color:<?php echo e($photo ? '#fff' : $colors['text']); ?>">
    <div class="h" style="font-size:<?php echo e((int) round($width * (mb_strlen($headline) > 28 ? 0.075 : 0.095))); ?>px"><?php echo e($headline); ?></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cta): ?><div style="margin-top:40px"><span class="cta"><?php echo e($cta); ?></span></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('render._base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/render/cover.blade.php ENDPATH**/ ?>