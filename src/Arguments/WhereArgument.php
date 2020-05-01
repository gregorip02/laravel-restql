<?php

namespace Restql\Arguments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Restql\Argument;

class WhereArgument extends Argument
{
    /**
     * The argument default keys.
     *
     * @var array
     */
    public $keys = ['column', 'operator', 'value'];

    /**
     * The argument default values.
     *
     * @var array
     */
    public $defaults = ['id', '=', null];

    /**
     * Merge the user argument values with defaults data.
     *
     * @return array
     */
    public function data(): array
    {
        if (! $this->isAssociative() && $this->countValues() === 1) {
            $this->defaults[2] = $this->values->first();

            return $this->combineAssociativeValues();
        }

        return parent::data();
    }
}
