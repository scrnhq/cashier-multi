<?php

namespace ScrnHQ\Cashier\Tests\Fixtures;

use ScrnHQ\Cashier\Billable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use Billable;
}