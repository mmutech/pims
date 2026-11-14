<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDistributionFixtures;
use Tests\Concerns\BuildsProcurementFixtures;
use Tests\Concerns\BuildsReceivingFixtures;
use Tests\Concerns\BuildsReportingFixtures;
use Tests\Concerns\BuildsStocktakingFixtures;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->use(BuildsProcurementFixtures::class)
    ->in('Feature/Procurement');

pest()->use(BuildsReceivingFixtures::class)
    ->in('Feature/Receiving');

pest()->use(BuildsDistributionFixtures::class)
    ->in('Feature/Distribution');

pest()->use(BuildsStocktakingFixtures::class)
    ->in('Feature/Stocktaking');

pest()->use(BuildsReportingFixtures::class)
    ->in('Feature/Reporting');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
