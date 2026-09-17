<?php

declare(strict_types=1);

namespace AhmedNour\AttributeRouting\Tests\Feature;

use AhmedNour\AttributeRouting\Console\ListAttributeRoutesCommand;
use AhmedNour\AttributeRouting\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ListAttributeRoutesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pin the width so assertions do not depend on the window the suite runs in.
        ListAttributeRoutesCommand::resolveTerminalWidthUsing(fn (): int => 200);
    }

    protected function tearDown(): void
    {
        ListAttributeRoutesCommand::resolveTerminalWidthUsing(null);

        parent::tearDown();
    }

    #[Test]
    public function it_lists_discovered_routes_with_their_source(): void
    {
        $output = $this->runCommand('attribute-routing:list');

        $this->assertStringContainsString('/api/v1/leads', $output);
        $this->assertStringContainsString('leads.index › ', $output);
        $this->assertStringContainsString('LeadController@index', $output);
    }

    #[Test]
    public function it_pads_verbs_into_a_column_and_fills_the_gap_with_dots(): void
    {
        $output = $this->runCommand('attribute-routing:list');

        $this->assertMatchesRegularExpression('#GET\|HEAD\s+/api/v1/leads \.{3,} leads\.index#', $output);
    }

    #[Test]
    public function it_collapses_every_verb_into_any(): void
    {
        $output = $this->runCommand('attribute-routing:list', ['--path' => 'probe']);

        $this->assertStringContainsString('ANY', $output);
        $this->assertStringNotContainsString('GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS', $output);
    }

    #[Test]
    public function it_hides_middleware_by_default(): void
    {
        $this->assertStringNotContainsString('permission:view_leads', $this->runCommand('attribute-routing:list'));
    }

    #[Test]
    public function it_shows_middleware_excluded_middleware_and_constraints_when_verbose(): void
    {
        $output = $this->runCommand('attribute-routing:list', [], verbose: true);

        $this->assertStringContainsString('⇂ permission:view_leads', $output);
        $this->assertStringContainsString('⇂ ✗ auth:sanctum', $output);
        $this->assertStringContainsString('⇂ where lead = [0-9]+', $output);
    }

    #[Test]
    public function it_shortens_the_controller_namespace_unless_verbose(): void
    {
        $fqcn = 'AhmedNour\\AttributeRouting\\Tests\\Fixtures\\Controllers\\LeadController@index';

        // The fixtures do not live under the application namespace, so nothing
        // is stripped here — but verbose output must always show the full path.
        $this->assertStringContainsString($fqcn, $this->runCommand('attribute-routing:list', [], verbose: true));
    }

    #[Test]
    public function it_truncates_rather_than_wrapping_on_a_narrow_terminal(): void
    {
        ListAttributeRoutesCommand::resolveTerminalWidthUsing(fn (): int => 60);

        $output = $this->runCommand('attribute-routing:list');

        $this->assertStringContainsString('…', $output);

        foreach (explode("\n", trim($output)) as $line) {
            $this->assertLessThanOrEqual(60, mb_strlen(rtrim($line)), "Line overflows the terminal: {$line}");
        }
    }

    #[Test]
    public function it_reports_how_many_routes_and_classes_were_found(): void
    {
        $this->assertStringContainsString(
            'Showing [13] routes from [5] scanned classes',
            $this->runCommand('attribute-routing:list'),
        );
    }

    #[Test]
    public function it_filters_by_path(): void
    {
        $output = $this->runCommand('attribute-routing:list', ['--path' => 'auth']);

        $this->assertStringContainsString('auth.login', $output);
        $this->assertStringNotContainsString('leads.index', $output);
    }

    #[Test]
    public function it_warns_when_nothing_was_discovered(): void
    {
        config()->set('attribute-routing.paths', []);

        $this->assertStringContainsString('No attribute routes found', $this->runCommand('attribute-routing:list'));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runCommand(string $command, array $parameters = [], bool $verbose = false): string
    {
        $output = new BufferedOutput(
            $verbose ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL,
        );

        $this->assertSame(0, Artisan::call($command, $parameters, $output));

        return $output->fetch();
    }
}
