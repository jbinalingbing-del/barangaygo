<?php
// Pagpipilian ng serbisyo sa itaas ng request form para madaling lumipat ng serbisyo
$active_service = $active_service ?? '';
$service_items = [
    'clearance' => ['label' => 'Barangay Clearance',          'href' => 'request_clearance.php'],
    'residency' => ['label' => 'Certificate of Residency',    'href' => 'request_residency.php'],
    'indigency' => ['label' => 'Certificate of Indigency',    'href' => 'request_indigency.php'],
    'business'  => ['label' => 'Barangay Business Clearance', 'href' => 'request_business.php'],
];
?>
<div class="service-switcher" role="navigation" aria-label="Barangay Services">
    <!-- Ipakita ang apat na serbisyo at lagyan ng 'active' ang kasalukuyang binubuksan -->
    <?php foreach ($service_items as $key => $item): ?>
        <a href="<?= $item['href']; ?>" class="service-tab<?= $active_service === $key ? ' active' : ''; ?>">
            <?= htmlspecialchars($item['label'], ENT_QUOTES); ?>
        </a>
    <?php endforeach; ?>
</div>
