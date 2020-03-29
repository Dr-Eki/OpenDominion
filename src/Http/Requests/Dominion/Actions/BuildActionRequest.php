<?php

namespace OpenDominion\Http\Requests\Dominion\Actions;

use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Http\Requests\Dominion\AbstractDominionRequest;

class BuildActionRequest extends AbstractDominionRequest
{
    /** @var BuildingHelper */
    protected $buildingHelper;

    /**
     * ConstructActionRequest constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->buildingHelper = app(BuildingHelper::class);
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'key' => 'required',
            'amount' => 'required'
        ];
    }
}
