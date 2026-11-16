<?php

namespace App\Http\Requests;

/**
 * Updating a dentist payment is held to exactly the rules storing one is. Kept as its
 * own class so the controller and the MCP tool can still name the action.
 */
class UpdateDentistPaymentRequest extends StoreDentistPaymentRequest {}
