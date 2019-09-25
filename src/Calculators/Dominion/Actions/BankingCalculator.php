<?php

namespace OpenDominion\Calculators\Dominion\Actions;

use OpenDominion\Models\Dominion;

class BankingCalculator
{
    /**
     * Returns resources and prices for exchanging.
     *
     * @param Dominion $dominion
     * @return array
     */
    public function getResources(Dominion $dominion): array
    {
        $resources = [
            'resource_platinum' => [
                'label' => 'Platinum',
                'buy' => 1.0,
                'sell' => 0.5,
                'max' => $dominion->resource_platinum,
            ],
            'resource_lumber' => [
                'label' => 'Lumber',
                'buy' => 1.0,
                'sell' => 0.5,
                'max' => $dominion->resource_lumber,
            ],
            'resource_ore' => [
                'label' => 'Ore',
                'buy' => 1.0,
                'sell' => 0.5,
                'max' => $dominion->resource_ore,
            ],
            'resource_gems' => [
                'label' => 'Gems',
                'buy' => 0.0,
                'sell' => 2.0,
                'max' => $dominion->resource_gems,
            ],
            'resource_food' => [
                'label' => 'Food',
                'buy' => 0.5,
                'sell' => 0.0,
                'max' => $dominion->resource_food,
            ],
        ];

        if($dominion->race->name == 'Demon')
        {
          $demonResources = [
            'resource_soul' => [
                'label' => 'Souls',
                'buy' => 1.0,
                'sell' => 0.0,
                'max' => $dominion->resource_gems,
            ],
            'resource_peasant' => [
                'label' => 'Peasants',
                'buy' => 3.0,
                'sell' => 0.0,
                'max' => $dominion->resource_food,
            ],
          ];

          $resources = array_merge($resources, $demonResources);

        }

        // Get racial bonus
        $bonus = $dominion->race->getPerkMultiplier('exchange_bonus');

        $resources['resource_platinum']['sell'] *= (1 + $bonus);
        $resources['resource_lumber']['sell'] *= (1 + $bonus);
        $resources['resource_ore']['sell'] *= (1 + $bonus);
        $resources['resource_gems']['sell'] *= (1 + $bonus);
        $resources['resource_food']['sell'] *= (1 + $bonus);

        return $resources;
    }
}
