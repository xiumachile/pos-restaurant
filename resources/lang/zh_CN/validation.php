<?php

return [
    'order' => [
        'type_required' => '订单类型是必填项。',
        'type_invalid' => '订单类型必须是 dine_in（堂食）、takeout（外带）或 delivery（外卖）。',
        'channel_invalid' => '履行渠道必须是 onsite（现场）、pickup（自取）或 delivery（配送）。',
        'table_required' => '堂食订单必须指定餐桌。',
        'table_not_found' => '指定的餐桌不存在。',
        'table_uuid_invalid' => '餐桌 UUID 无效。',
        'status_invalid' => '订单状态必须是 draft、confirmed、preparing、ready 或 served。',
        'customer_name_required' => '外卖订单必须填写客户姓名。',
        'customer_phone_required' => '外卖订单必须填写客户电话。',
        'delivery_address_required' => '外卖订单必须填写配送地址。',
        'customer_not_found' => '指定的客户不存在。',
        'customer_id_required' => '客户 ID 是必填项。',
        'pickup_date_invalid' => '取货时间必须是有效的日期。',
        'pickup_date_past' => '取货时间必须等于或晚于当前时间。',
        'delivery_channel_mismatch' => '外卖订单的渠道只能是 delivery。',
        'takeout_no_table' => '外带订单不能指定餐桌。',
        'delivery_no_table' => '外卖订单不能指定餐桌。',
        'delivery_no_pickup' => '外卖订单不能设置取货时间，请使用配送地址。',
        'dinein_no_delivery_address' => '堂食订单不能设置配送地址。',
    ],
];
