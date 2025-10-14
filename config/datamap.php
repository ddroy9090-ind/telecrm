<?php

declare(strict_types=1);

/**
 * Mapping between dashboard concepts and the actual database schema.
 *
 * Notes & assumptions:
 * - Leads are stored in `all_leads`. Assigned agents are stored as free-form
 *   names in the `assigned_to` column (matching `users.full_name`).
 * - Lead activities live in `lead_activity_log` and are linked to leads via
 *   `lead_id`.
 * - Projects / inventory level details are sourced from `properties_list`.
 *   Some columns such as total / sold units may not be present in every
 *   installation; the repositories check column availability at runtime.
 */
return [
    'users' => [
        'table'   => 'users',
        'columns' => [
            'id'         => 'id',
            'name'       => 'full_name',
            'email'      => 'email',
            'role'       => 'role',
            'phone'      => 'contact_number',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ],
    ],
    'leads' => [
        'table'   => 'all_leads',
        'sources_table' => 'lead_sources',
        'columns' => [
            'id'               => 'id',
            'stage'            => 'stage',
            'rating'           => 'rating',
            'assigned_to'      => 'assigned_to',
            'source'           => 'source',
            'name'             => 'name',
            'phone'            => 'phone',
            'email'            => 'email',
            'alternate_phone'  => 'alternate_phone',
            'nationality'      => 'nationality',
            'interested_in'    => 'interested_in',
            'property_type'    => 'property_type',
            'location'         => 'location_preferences',
            'budget_range'     => 'budget_range',
            'purpose'          => 'purpose',
            'urgency'          => 'urgency',
            'created_by_id'    => 'created_by',
            'created_by_name'  => 'created_by_name',
            'created_at'       => 'created_at',
            'payout_received'  => 'payout_received',
        ],
    ],
    'channel_partners' => [
        'table'   => 'channel_partners',
        'columns' => [
            'id'         => 'id',
            'name'       => 'partner_name',
            'status'     => 'status',
            'created_at' => 'created_at',
        ],
    ],
    'lead_activity' => [
        'table'   => 'lead_activity_log',
        'columns' => [
            'id'             => 'id',
            'lead_id'        => 'lead_id',
            'type'           => 'activity_type',
            'description'    => 'description',
            'metadata'       => 'metadata',
            'created_by_id'  => 'created_by',
            'created_by_name'=> 'created_by_name',
            'created_at'     => 'created_at',
        ],
    ],
    'projects' => [
        'table'   => 'properties_list',
        'columns' => [
            'id'                 => 'id',
            'name'               => 'project_name',
            'title'              => 'property_title',
            'location'           => 'property_location',
            'type'               => 'property_type',
            'starting_price'     => 'starting_price',
            'total_area'         => 'total_area',
            'completion_date'    => 'completion_date',
            'booking_percentage' => 'booking_percentage',
            'booking_amount'     => 'booking_amount',
            'category'           => 'category',
            'created_at'         => 'created_at',
        ],
    ],
];
