
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
body{font-family:'Liberation Sans',Arial,sans-serif;color:#1f2937;font-size:11pt;line-height:1.45}
h1{font-size:18pt;margin:0 0 4pt} h2{font-size:12.5pt;margin:18pt 0 6pt;border-bottom:1px solid #d1d5db;padding-bottom:3pt}
.muted{color:#6b7280;font-size:9.5pt} table{width:100%;border-collapse:collapse;font-size:10pt}
th,td{text-align:left;padding:5pt 6pt;border-bottom:1px solid #e5e7eb} th{background:#f3f4f6}
td.n{text-align:right;font-variant-numeric:tabular-nums} .up{color:#047857}.down{color:#b91c1c}
ul{margin:4pt 0 0 14pt;padding:0} .primary td{font-weight:bold}
</style>
</head>
<body>
<div class="muted">Laporan mingguan · <?php echo e($client->name); ?></div>
<h1><?php echo e($narrative['headline'] ?? 'Ringkasan kinerja'); ?></h1>
<div class="muted">Periode <?php echo e($period['from']); ?> s.d. <?php echo e($period['to']); ?> · dibandingkan <?php echo e($period['compare_from']); ?> s.d. <?php echo e($period['compare_to']); ?></div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($narrative['summary'])): ?>
<h2>Ringkasan</h2>
<p><?php echo e($narrative['summary']); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<h2>Angka utama</h2>
<table>
<thead><tr><th>Metrik</th><th>Sumber</th><th class="n">Periode ini</th><th class="n">Sebelumnya</th><th class="n">Perubahan</th><th class="n">Target</th></tr></thead>
<tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
<tr class="<?php echo \Illuminate\Support\Arr::toCssClasses(['primary' => $row['is_primary_goal'] ?? false]); ?>">
    <td><?php echo e($row['label'] ?? $row['metric_key']); ?></td>
    <td><?php echo e($row['source']); ?></td>
    <td class="n"><?php echo e(number_format((float) $row['value'], 0, ',', '.')); ?></td>
    <td class="n"><?php echo e($row['previous'] === null ? '—' : number_format((float) $row['previous'], 0, ',', '.')); ?></td>
    <td class="n <?php echo e(($row['change_pct'] ?? 0) > 0 ? 'up' : (($row['change_pct'] ?? 0) < 0 ? 'down' : '')); ?>"><?php echo e($row['change_pct'] === null ? '—' : number_format((float) $row['change_pct'], 1, ',', '.').'%'); ?></td>
    <td class="n"><?php echo e(($row['target'] ?? null) === null ? '—' : number_format((float) $row['target'], 0, ',', '.')); ?></td>
</tr>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</tbody>
</table>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['highlights' => 'Sorotan', 'concerns' => 'Perlu perhatian', 'next_steps' => 'Rencana minggu depan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($narrative[$key])): ?>
    <h2><?php echo e($title); ?></h2>
    <ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $narrative[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($line); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($quality)): ?>
<h2>Catatan data</h2>
<ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $quality; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li class="muted"><?php echo e($line); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($narrative)): ?>
<p class="muted">Komentar otomatis tidak tersedia minggu ini; angka di atas tetap dihitung dari data.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/reports/weekly.blade.php ENDPATH**/ ?>