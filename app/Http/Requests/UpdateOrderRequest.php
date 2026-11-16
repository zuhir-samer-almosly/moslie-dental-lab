<?php

namespace App\Http\Requests;

/**
 * Updating an order is held to exactly the rules storing one is. Kept as its
 * own class so the controller and the MCP tool can still name the action.
 */
class UpdateOrderRequest extends StoreOrderRequest {}
