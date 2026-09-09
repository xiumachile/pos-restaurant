<?php

use Modules\Fiscal\Domain\ValueObjects\DteType;
use Modules\Orders\Domain\Entities\Order;

function makeOrderWithTaxSnapshotsForDteTest(array $snapshots): Order
{
    $order = new Order();

    $items = collect(array_map(function ($snapshot) {
        return (object) [
            'tax_rate_snapshot' => $snapshot,
        ];
    }, $snapshots));

    $order->setRelation('items', $items);

    return $order;
}

test('DteType usa snapshot histórico 19% desde OrderItem', function () {
    $order = makeOrderWithTaxSnapshotsForDteTest([19.0000]);

    $rate = DteType::BOLETA_AFECTA->taxRateFromOrder($order);

    expect(abs($rate - 0.19))->toBeLessThan(0.000001);
});

test('DteType usa snapshot histórico 21% si la tasa cambió antes de crear la orden', function () {
    $order = makeOrderWithTaxSnapshotsForDteTest([21.0000]);

    $rate = DteType::BOLETA_AFECTA->taxRateFromOrder($order);

    expect(abs($rate - 0.21))->toBeLessThan(0.000001);
});

test('DteType exento retorna 0 aunque existan items con snapshot afecto', function () {
    $order = makeOrderWithTaxSnapshotsForDteTest([19.0000]);

    $rate = DteType::BOLETA_EXENTA->taxRateFromOrder($order);

    expect($rate)->toBe(0.0);
});

test('DteType retorna 0 si la orden no tiene items afectos', function () {
    $order = makeOrderWithTaxSnapshotsForDteTest([0.0000, null]);

    $rate = DteType::BOLETA_AFECTA->taxRateFromOrder($order);

    expect($rate)->toBe(0.0);
});

test('DteType usa el primer snapshot afecto cuando hay items mixtos', function () {
    $order = makeOrderWithTaxSnapshotsForDteTest([0.0000, 21.0000, 19.0000]);

    $rate = DteType::FACTURA_ELECTRONICA->taxRateFromOrder($order);

    expect(abs($rate - 0.21))->toBeLessThan(0.000001);
});

test('DteType taxRate legacy no hardcodea 19%', function () {
    expect(DteType::BOLETA_AFECTA->taxRate())->toBe(0.0);
    expect(DteType::FACTURA_ELECTRONICA->taxRate())->toBe(0.0);
    expect(DteType::BOLETA_EXENTA->taxRate())->toBe(0.0);
});
