<?php

namespace App\Http\Requests\Hr;

/**
 * Same rule set as creation — the unique constraints already ignore the
 * current record via the route binding.
 */
class UpdateEmployeeRequest extends StoreEmployeeRequest {}
