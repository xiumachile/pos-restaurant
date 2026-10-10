<?php

return [
    'order' => [
        'type_required' => 'El tipo de pedido es obligatorio.',
        'type_invalid' => 'El tipo de pedido debe ser dine_in, takeout o delivery.',
        'channel_invalid' => 'El canal de cumplimiento debe ser onsite, pickup o delivery.',
        'table_required' => 'Los pedidos dine_in requieren una mesa.',
        'table_not_found' => 'La mesa especificada no existe.',
        'table_uuid_invalid' => 'El UUID de la mesa es inválido.',
        'status_invalid' => 'El estado del pedido debe ser draft, confirmed, preparing, ready o served.',
        'customer_name_required' => 'El nombre del cliente es requerido para delivery.',
        'customer_phone_required' => 'El teléfono del cliente es requerido para delivery.',
        'delivery_address_required' => 'La dirección de entrega es requerida para delivery.',
        'customer_not_found' => 'El cliente especificado no existe.',
        'customer_id_required' => 'El ID del cliente es requerido.',
        'pickup_date_invalid' => 'La hora de retiro debe ser una fecha válida.',
        'pickup_date_past' => 'La hora de retiro debe ser igual o posterior a ahora.',
        'delivery_channel_mismatch' => 'Los pedidos delivery solo pueden tener canal delivery.',
        'takeout_no_table' => 'Los pedidos takeout no pueden tener mesa asignada.',
        'delivery_no_table' => 'Los pedidos delivery no pueden tener mesa asignada.',
        'delivery_no_pickup' => 'Los pedidos delivery no pueden tener hora de retiro.',
        'dinein_no_delivery_address' => 'Los pedidos dine_in no pueden tener dirección de delivery.',
    ],
];
