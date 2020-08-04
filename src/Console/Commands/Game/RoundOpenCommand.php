<?php

namespace OpenDominion\Console\Commands\Game;

use Carbon\Carbon;
use Illuminate\Console\Command;
use OpenDominion\Console\Commands\CommandInterface;
use OpenDominion\Factories\RealmFactory;
use OpenDominion\Factories\RoundFactory;
use OpenDominion\Models\RoundLeague;
use RuntimeException;

use OpenDominion\Services\BarbarianService;

class RoundOpenCommand extends Command implements CommandInterface
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'game:round:open
                             {--now : Start the round right now (dev & testing only)}
                             {--open : Start the round in +3 days midnight, allowing for immediate registration}
                             {--days= : Start the round in +DAYS days midnight, allowing for more fine-tuning}
                             {--league=standard : Round league to use}
                             {--realm-size=10 : Maximum number of dominions in one realm}
                             {--pack-size=4 : Maximum number of players in a pack}
                             {--playersPerRace=2 : Maximum number of players using the same race, 0 = unlimited}
                             {--mixedAlignment=true : Allows for mixed alignments}';

    /** @var string The console command description. */
    protected $description = 'Creates a new round which starts in 5 days';

    /** @var RealmFactory */
    protected $realmFactory;

    /** @var RoundFactory */
    protected $roundFactory;

    /** @var BarbarianService */
    protected $barbarianService;

    /**
     * RoundOpenCommand constructor.
     *
     * @param RoundFactory $roundFactory
     * @param RealmFactory $realmFactory
     */
    public function __construct(
        RoundFactory $roundFactory,
        RealmFactory $realmFactory,
        BarbarianService $barbarianService
    ) {
        parent::__construct();

        $this->roundFactory = $roundFactory;
        $this->realmFactory = $realmFactory;
        $this->barbarianService = $barbarianService;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        $now = $this->option('now');
        $open = $this->option('open');
        $days = $this->option('days');
        $league = $this->option('league');
        $realmSize = 100; #$this->option('realm-size');
        $packSize = 100;#$this->option('pack-size');
        $playersPerRace = 0; #$this->option('playersPerRace');
        $mixedAlignments = false; #$this->option('mixedAlignment');

        if ($now && (app()->environment() === 'production')) {
            throw new RuntimeException('Option --now may not be used on production');
        }

        if (($now && $open) || ($now && $days) || ($open && $days)) {
            throw new RuntimeException('Options --now, --open and --days are mutually exclusive');
        }

        if ($realmSize <= 0) {
            throw new RuntimeException('Option --realm-size must be greater than 0.');
        }

        if ($packSize <= 0) {
            throw new RuntimeException('Option --pack-size must be greater than 0.');
        }

        if ($realmSize < $packSize) {
            throw new RuntimeException('Option --realm-size must be greater than or equal to option --packSize.');
        }

        if ($playersPerRace < 0) {
            throw new RuntimeException('Option --playersPerRace must be greater than or equal to 0.');
        }

        if ($now) {
            $startDate = 'now';

        } elseif ($open) {
            $startDate = '+3 days midnight';

        } elseif ($days !== null) {
            if (!ctype_digit($days)) {
                throw new RuntimeException('Option --days=DAYS must be an integer');
            }

            $startDate = "+{$days} days midnight";

        } else {
            $startDate = '+5 days midnight';
        }

        $startDate = new Carbon($startDate);

        /** @var RoundLeague $roundLeague */
        $roundLeague = RoundLeague::where('key', $league)->firstOrFail();

        $this->info("Starting a new round in {$roundLeague->key} league");

        $round = $this->roundFactory->create(
            $roundLeague,
            $startDate,
            $realmSize,
            $packSize,
            $playersPerRace,
            $mixedAlignments
        );

        $this->info("Round {$round->number} created in Era {$roundLeague->key}. The round starts at {$round->start_date} and ends at {$round->end_date}.");

        // Prepopulate round with #1 Barbarian, #2 Commonwealth, #3 Empire, #4 Independent
        $this->realmFactory->create($round, 'npc');
        $this->realmFactory->create($round, 'good');
        $this->realmFactory->create($round, 'evil');
        $this->realmFactory->create($round, 'independent');

        /*
        if ($round->mixed_alignment) {
            // Prepopulate round with 20 mixed realms
            for ($i = 1; $i <= 20; $i++) {
                $realm = $this->realmFactory->create($round);
                $this->info("Realm {$realm->name} (#{$realm->number}) created in Round {$round->number} with an alignment of {$realm->alignment}");
            }
        } else {
            // Prepopulate round with 5 good and 5 evil realms
            for ($i = 1; $i <= 5; $i++) {
                $realm = $this->realmFactory->create($round, 'good');
                $this->info("Realm {$realm->name} (#{$realm->number}) created in Round {$round->number} with an alignment of {$realm->alignment}");

                $realm = $this->realmFactory->create($round, 'evil');
                $this->info("Realm {$realm->name} (#{$realm->number}) created in Round {$round->number} with an alignment of {$realm->alignment}");
            }
        }
        */

        // Create 15 Barbarians.
        for ($slot = 1; $slot <= 15; $slot++)
        {
            $this->barbarianService->createBarbarian($round);
        }

    }
}
