<?php

namespace OpenDominion\Services\Dominion;

use Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use OpenDominion\Models\Dominion;
use OpenDominion\Models\GameEvent;
use OpenDominion\Models\Realm;

use OpenDominion\Calculators\Dominion\MilitaryCalculator;
use OpenDominion\Calculators\Dominion\LandCalculator;

class BarbarianService
{

    /** @var MilitaryCalculator */
    protected $militaryCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var QueueService */
    protected $queueService;

    /**
     * BarbarianService constructor.
     */
    public function __construct()
    {
        #$this->now = now();
        $this->landCalculator = app(LandCalculator::class);
        $this->queueService = app(QueueService::class);
        $this->militaryCalculator = app(MilitaryCalculator::class);
    }


    private function getDpaTarget(Dominion $dominion): int
    {
        $constant = 25;
        $hoursIntoTheRound = now()->startOfHour()->diffInHours(Carbon::parse($dominion->round->start_date)->startOfHour());
        $dpa = $constant + ($hoursIntoTheRound * 0.40);
        return $dpa *= ($dominion->npc_modifier / 1000);
    }

    private function getOpaTarget(Dominion $dominion): int
    {
        return $this->getDpaTarget($dominion) * 0.75;
    }

    # Includes units out on attack.
    private function getDpCurrent(Dominion $dominion): int
    {
        $dp = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 2) * 3;
        $dp += $this->militaryCalculator->getTotalUnitsForSlot($dominion, 3) * 5;

        return $dp;
    }

    # Includes units at home and out on attack.
    private function getOpCurrent(Dominion $dominion): int
    {
        $op = $this->militaryCalculator->getTotalUnitsForSlot($dominion, 1) * 3;
        $op += $this->militaryCalculator->getTotalUnitsForSlot($dominion, 4) * 5;

        return $op;
    }

    # Includes units at home and out on attack.
    private function getOpAtHome(Dominion $dominion): int
    {
        $op = $dominion->military_unit1 * 3;
        $op += $dominion->military_unit4 * 5;

        return $op;
    }

    private function getDpPaid(Dominion $dominion): int
    {
        $dp = $this->getDpCurrent($dominion);
        $dp += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit2') * 3;
        $dp += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit3') * 5;

        return $dp;
    }

    private function getOpPaid(Dominion $dominion): int
    {
        $op = $this->getOpCurrent($dominion);
        $op += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit1') * 3;
        $op += $this->queueService->getTrainingQueueTotalByResource($dominion, 'military_unit4') * 5;

        return $op;
    }


    private function getDpaCurrent(Dominion $dominion): int
    {
        return $this->getDpCurrent($dominion) / $this->landCalculator->getTotalLand($dominion);
    }

    private function getOpaCurrent(Dominion $dominion): int
    {
        return $this->getOpCurrent($dominion) / $this->landCalculator->getTotalLand($dominion);
    }


    private function getDpaPaid(Dominion $dominion): int
    {
        return $this->getDpPaid($dominion) / $this->landCalculator->getTotalLand($dominion);
    }

    private function getOpaPaid(Dominion $dominion): int
    {
        return $this->getOpPaid($dominion) / $this->landCalculator->getTotalLand($dominion);
    }

    private function getOpaAtHome(Dominion $dominion): int
    {
        return $this->getOpAtHome($dominion) / $this->landCalculator->getTotalLand($dominion);
    }


    public function handleBarbarianTraining(Dominion $dominion): void
    {
        if($dominion->race->name === 'Barbarian')
        {
            $land = $this->landCalculator->getTotalLand($dominion);

            $units = [
              'military_unit1' => 0,
              'military_unit2' => 0,
              'military_unit3' => 0,
              'military_unit4' => 0,
            ];

            $dpaDelta = $this->getDpaTarget($dominion) - $this->getDpaPaid($dominion);

            if($dpaDelta > 0)
            {
                #echo "[DP] Need to train DP. DPA delta is: $dpaDelta (current: " . $this->getDpaTarget($dominion) . " - paid: " . $this->getDpaPaid($dominion) . ")\n";
                $dpToTrain = $dpaDelta * $land;

                $specsRatio = rand(50,500)/1000;
                $elitesRatio = 1-$specsRatio;

                $units['military_unit2'] = intval(($dpToTrain*$specsRatio)/3);
                $units['military_unit3'] = intval(($dpToTrain*$specsRatio)/5);
            }
            else
            {
                #echo "[DP] No need to train DP. DPA delta is: $dpaDelta (current: " . $this->getDpaTarget($dominion) . " - paid: " . $this->getDpaPaid($dominion) . ")\n";
            }

            $opaDelta = $this->getOpaTarget($dominion) - $this->getOpaPaid($dominion);

            if($opaDelta > 0)
            {
                #echo "[OP] Need to train OP. OPA delta is: $opaDelta (current: " . $this->getOpaTarget($dominion) . " - paid: " . $this->getOpaPaid($dominion) . ")\n";

                $opToTrain = $opaDelta * $land;

                $specsRatio = rand(50,500)/1000;
                $elitesRatio = 1-$specsRatio;

                $units['military_unit1'] = intval(($opToTrain*$specsRatio)/3);
                $units['military_unit4'] = intval(($opToTrain*$specsRatio)/5);
            }
            else
            {
                #echo "[OP] No need to train OP. OPA delta is: $opaDelta (current: " . $this->getOpaTarget($dominion) . " - paid: " . $this->getOpaPaid($dominion) . ")\n";
            }

            foreach($units as $unit => $amountToTrain)
            {
                if($amountToTrain > 0)
                {
                    # Randomly train between 25% and 75% of the units needed.
                    $amountToTrain = max(1, intval($amountToTrain * (rand(250,750)/1000)));
                    #echo "[TRAINING] " . number_format($amountToTrain) . ' ' . $unit. "\n";
                    $data = [$unit => $amountToTrain];
                    $hours = 12;
                    $this->queueService->queueResources('training', $dominion, $data, $hours);
                }
            }

        }

    }

    public function handleBarbarianInvasion(Dominion $dominion): void
    {
        $invade = false;

        if($dominion->race->name === 'Barbarian')
        {
            // Make sure we have the expected OPA to hit.
            if($this->getOpaAtHome($dominion) >= $this->getOpaTarget($dominion))
            {
                #echo "[INVADE] Sufficient OPA to invade. (home: " . $this->getOpaAtHome($dominion) . ", target:" . $this->getOpaTarget($dominion) . ", paid: " . $this->getOpaPaid($dominion) .")\n";

                $currentDay = $dominion->round->start_date->subDays(1)->diffInDays(now());
                $chanceOneIn = 1;#32 - (14 - min($currentDay, 14));
                if(rand(1,$chanceOneIn) == 1)
                {
                    $invade = true;
                    #echo "[INVADE] Invasion confirmed to take place.\n";
                }
            }
            else
            {
                #echo "[INVADE] Not enough OPA to invade. (home: " . $this->getOpaAtHome($dominion) . ", target:" . $this->getOpaTarget($dominion) . ", paid: " . $this->getOpaPaid($dominion) .")\n";
            }

            if($invade === true)
            {
                # Grow by 5-12.5% (random), skewed to lower.
                $landGainRatio = max(500,rand(400,1250))/10000;

                # Calculate the amount of acres to grow.
                $totalLandToGain = intval($this->landCalculator->getTotalLand($dominion) * $landGainRatio);

                # Split the land gained evenly across all 6 land types.
                $landGained['land_plain'] = intval($totalLandToGain/6);
                $landGained['land_mountain'] = intval($totalLandToGain/6);
                $landGained['land_forest'] = intval($totalLandToGain/6);
                $landGained['land_swamp'] = intval($totalLandToGain/6);
                $landGained['land_hill'] = intval($totalLandToGain/6);
                $landGained['land_water'] = intval($totalLandToGain/6);

                # Add the land gained to the $dominion.
                $dominion->stat_total_land_conquered = $totalLandToGain;
                $dominion->stat_attacking_success += 1;

                # Send out 80-100% of all units. Random over 100 but capped at 100 to make it more likely 100% are sent.
                $sentRatio = min(1000,rand(800,1250))/1000;

                # Casualties between 8.5% and 12% (random).
                $casualtiesRatio = rand(85,120)/1000;

                # Calculate how many Unit1 and Unit4 are sent.
                $unitsSent['military_unit1'] = $dominion->military_unit1 * $sentRatio;
                $unitsSent['military_unit4'] = $dominion->military_unit4 * $sentRatio;

                # Remove the sent units from the dominion.

                #echo "Unit1 before sending: " . number_format($dominion->military_unit1) . "\n";
                #echo "Unit4 before sending: " . number_format($dominion->military_unit4) . "\n";

                #echo "Unit1 to send: " . number_format($unitsSent['military_unit1']) . "\n";
                #echo "Unit4 to send: " . number_format($unitsSent['military_unit4']) . "\n";

                $dominion->military_unit1 -= $unitsSent['military_unit1'];
                $dominion->military_unit4 -= $unitsSent['military_unit4'];

                #echo "Unit1 after sending: " . number_format($dominion->military_unit1) . "\n";
                #echo "Unit4 after sending: " . number_format($dominion->military_unit4) . "\n";

                # Calculate losses by applying casualties ratio to units sent.
                $unitsLost['military_unit1'] = $unitsSent['military_unit1'] * $casualtiesRatio;
                $unitsLost['military_unit4'] = $unitsSent['military_unit4'] * $casualtiesRatio;

                # Calculate amount of returning units.
                $unitsReturning['military_unit1'] = intval(max($unitsSent['military_unit1'] - $unitsLost['military_unit1'],0));
                $unitsReturning['military_unit4'] = intval(max($unitsSent['military_unit4'] - $unitsLost['military_unit4'],0));

                #print_r($landGained);
                #print_r($unitsReturning);

                # Queue the incoming land.
                $this->queueService->queueResources(
                    'invasion',
                    $dominion,
                    $landGained
                );

                # Queue the returning units.
                $this->queueService->queueResources(
                    'invasion',
                    $dominion,
                    $unitsReturning
                );

               $invasionTypes = ['attacked', 'raided', 'pillaged', 'ransacked', 'looted', 'devastated', 'plundered'];
               $invasionTargets = ['settlement', 'village', 'town', 'hamlet', 'plot of unclaimed land', 'community', 'trading hub'];

               $data = [
                    'type' => $invasionTypes[rand(0,count($invasionTypes)-1)],
                    'target' => $invasionTargets[rand(0,count($invasionTargets)-1)],
                    'land' => $totalLandToGain,
                  ];

                $barbarianInvasionEvent = GameEvent::create([
                    'round_id' => $dominion->round_id,
                    'source_type' => Dominion::class,
                    'source_id' => $dominion->id,
                    'target_type' => Realm::class,
                    'target_id' => $dominion->realm_id,
                    'type' => 'barbarian_invasion',
                    'data' => $data,
                ]);
                $dominion->save(['event' => HistoryService::EVENT_ACTION_INVADE]);
            }
        }
    }

}