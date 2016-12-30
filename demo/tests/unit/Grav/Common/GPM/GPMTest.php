<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\GPM\GPM;

define('EXCEPTION_BAD_FORMAT', 1);
define('EXCEPTION_INCOMPATIBLE_VERSIONS', 2);

class GpmStub extends GPM
{
    public $data;

    public function findPackage($packageName)
    {
        if (isset($this->data[$packageName])) {
            return $this->data[$packageName];
        }

    }

    public function findPackages($searches = [])
    {
        return $this->data;
    }
}

/**
 * Class InstallCommandTest
 */
class GpmTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var GpmStub */
    protected $gpm;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->gpm = new GpmStub();
    }

    protected function _after()
    {
    }

    public function testCalculateMergedDependenciesOfPackages()
    {
        //////////////////////////////////////////////////////////////////////////////////////////
        // First working example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [
            'admin' => (object)[
                'dependencies' => [
                    ["name" => "grav", "version" => ">=1.0.10"],
                    ["name" => "form", "version" => "~2.0"],
                    ["name" => "login", "version" => ">=2.0"],
                    ["name" => "errors", "version" => "*"],
                    ["name" => "problems"],
                ]
            ],
            'test' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">=1.0"]
                ]
            ],
            'grav',
            'form' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">=3.2"]
                ]
            ]


        ];

        $packages = ['admin', 'test'];

        $dependencies = $this->gpm->calculateMergedDependenciesOfPackages($packages);

        $this->assertTrue(is_array($dependencies));
        $this->assertSame(5, count($dependencies));

        $this->assertTrue($dependencies['grav'] == '>=1.0.10');
        $this->assertTrue(isset($dependencies['errors']));
        $this->assertTrue(isset($dependencies['problems']));

        //////////////////////////////////////////////////////////////////////////////////////////
        // Second working example
        //////////////////////////////////////////////////////////////////////////////////////////
        $packages = ['admin', 'form'];

        $dependencies = $this->gpm->calculateMergedDependenciesOfPackages($packages);
        $this->assertTrue(is_array($dependencies));
        $this->assertSame(5, count($dependencies));
        $this->assertTrue($dependencies['errors'] == '>=3.2');

        //////////////////////////////////////////////////////////////////////////////////////////
        // Third working example
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [

            'admin' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">=4.0"],
                ]
            ],
            'test' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">=1.0"]
                ]
            ],
            'another' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">=3.2"]
                ]
            ]

        ];

        $packages = ['admin', 'test', 'another'];


        $dependencies = $this->gpm->calculateMergedDependenciesOfPackages($packages);
        $this->assertTrue(is_array($dependencies));
        $this->assertSame(1, count($dependencies));
        $this->assertTrue($dependencies['errors'] == '>=4.0');



        //////////////////////////////////////////////////////////////////////////////////////////
        // Test alpha / beta / rc
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [
            'admin' => (object)[
                'dependencies' => [
                    ["name" => "package1", "version" => ">=4.0.0-rc1"],
                    ["name" => "package4", "version" => ">=3.2.0"],
                ]
            ],
            'test' => (object)[
                'dependencies' => [
                    ["name" => "package1", "version" => ">=4.0.0-rc2"],
                    ["name" => "package2", "version" => ">=3.2.0-alpha"],
                    ["name" => "package3", "version" => ">=3.2.0-alpha.2"],
                    ["name" => "package4", "version" => ">=3.2.0-alpha"],
                ]
            ],
            'another' => (object)[
                'dependencies' => [
                    ["name" => "package2", "version" => ">=3.2.0-beta.11"],
                    ["name" => "package3", "version" => ">=3.2.0-alpha.1"],
                    ["name" => "package4", "version" => ">=3.2.0-beta"],
                ]
            ]
        ];

        $packages = ['admin', 'test', 'another'];


        $dependencies = $this->gpm->calculateMergedDependenciesOfPackages($packages);
        $this->assertTrue($dependencies['package1'] == '>=4.0.0-rc2');
        $this->assertTrue($dependencies['package2'] == '>=3.2.0-beta.11');
        $this->assertTrue($dependencies['package3'] == '>=3.2.0-alpha.2');
        $this->assertTrue($dependencies['package4'] == '>=3.2.0');


        //////////////////////////////////////////////////////////////////////////////////////////
        // Raise exception if no version is specified
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [

            'admin' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">=4.0"],
                ]
            ],
            'test' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => ">="]
                ]
            ],

        ];

        $packages = ['admin', 'test'];

        try {
            $this->gpm->calculateMergedDependenciesOfPackages($packages);
            $this->fail("Expected Exception not thrown");
        } catch (Exception $e) {
            $this->assertEquals(EXCEPTION_BAD_FORMAT, $e->getCode());
            $this->assertStringStartsWith("Bad format for version of dependency", $e->getMessage());
        }

        //////////////////////////////////////////////////////////////////////////////////////////
        // Raise exception if incompatible versions are specified
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [
            'admin' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => "~4.0"],
                ]
            ],
            'test' => (object)[
                'dependencies' => [
                    ["name" => "errors", "version" => "~3.0"]
                ]
            ],
        ];

        $packages = ['admin', 'test'];

        try {
            $this->gpm->calculateMergedDependenciesOfPackages($packages);
            $this->fail("Expected Exception not thrown");
        } catch (Exception $e) {
            $this->assertEquals(EXCEPTION_INCOMPATIBLE_VERSIONS, $e->getCode());
            $this->assertStringEndsWith("required in two incompatible versions", $e->getMessage());
        }

        //////////////////////////////////////////////////////////////////////////////////////////
        // Test dependencies of dependencies
        //////////////////////////////////////////////////////////////////////////////////////////
        $this->gpm->data = [
            'admin' => (object)[
                'dependencies' => [
                    ["name" => "grav", "version" => ">=1.0.10"],
                    ["name" => "form", "version" => "~2.0"],
                    ["name" => "login", "version" => ">=2.0"],
                    ["name" => "errors", "version" => "*"],
                    ["name" => "problems"],
                ]
            ],
            'login' => (object)[
                'dependencies' => [
                    ["name" => "antimatter", "version" => ">=1.0"]
                ]
            ],
            'grav',
            'antimatter' => (object)[
                'dependencies' => [
                    ["name" => "something", "version" => ">=3.2"]
                ]
            ]


        ];

        $packages = ['admin'];

        $dependencies = $this->gpm->calculateMergedDependenciesOfPackages($packages);

        $this->assertTrue(is_array($dependencies));
        $this->assertSame(7, count($dependencies));

        $this->assertSame('>=1.0.10', $dependencies['grav']);
        $this->assertTrue(isset($dependencies['errors']));
        $this->assertTrue(isset($dependencies['problems']));
        $this->assertTrue(isset($dependencies['antimatter']));
        $this->assertTrue(isset($dependencies['something']));
        $this->assertSame('>=3.2', $dependencies['something']);
    }

    public function testVersionFormatIsNextSignificantRelease()
    {
        $this->assertFalse($this->gpm->versionFormatIsNextSignificantRelease('>=1.0'));
        $this->assertFalse($this->gpm->versionFormatIsNextSignificantRelease('>=2.3.4'));
        $this->assertFalse($this->gpm->versionFormatIsNextSignificantRelease('>=2.3.x'));
        $this->assertFalse($this->gpm->versionFormatIsNextSignificantRelease('1.0'));
        $this->assertTrue($this->gpm->versionFormatIsNextSignificantRelease('~2.3.x'));
        $this->assertTrue($this->gpm->versionFormatIsNextSignificantRelease('~2.0'));
    }

    public function testVersionFormatIsEqualOrHigher()
    {
        $this->assertTrue($this->gpm->versionFormatIsEqualOrHigher('>=1.0'));
        $this->assertTrue($this->gpm->versionFormatIsEqualOrHigher('>=2.3.4'));
        $this->assertTrue($this->gpm->versionFormatIsEqualOrHigher('>=2.3.x'));
        $this->assertFalse($this->gpm->versionFormatIsEqualOrHigher('~2.3.x'));
        $this->assertFalse($this->gpm->versionFormatIsEqualOrHigher('1.0'));
    }

    public function testCheckNextSignificantReleasesAreCompatible()
    {
        /*
         * ~1.0     is equivalent to >=1.0 < 2.0.0
         * ~1.2     is equivalent to >=1.2 <2.0.0
         * ~1.2.3   is equivalent to >=1.2.3 <1.3.0
         */
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.0', '1.2'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.2', '1.0'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.0', '1.0.10'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.1', '1.1.10'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('30.0', '30.10'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.0', '1.1.10'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.0', '1.8'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('1.0.1', '1.1'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('2.0.0-beta', '2.0'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('2.0.0-rc.1', '2.0'));
        $this->assertTrue($this->gpm->checkNextSignificantReleasesAreCompatible('2.0', '2.0.0-alpha'));

        $this->assertFalse($this->gpm->checkNextSignificantReleasesAreCompatible('1.0', '2.2'));
        $this->assertFalse($this->gpm->checkNextSignificantReleasesAreCompatible('1.0.0-beta.1', '2.0'));
        $this->assertFalse($this->gpm->checkNextSignificantReleasesAreCompatible('0.9.99', '1.0.0'));
        $this->assertFalse($this->gpm->checkNextSignificantReleasesAreCompatible('0.9.99', '1.0.10'));
        $this->assertFalse($this->gpm->checkNextSignificantReleasesAreCompatible('0.9.99', '1.0.10.2'));
    }


    public function testCalculateVersionNumberFromDependencyVersion()
    {
        $this->assertSame('2.0', $this->gpm->calculateVersionNumberFromDependencyVersion('>=2.0'));
        $this->assertSame('2.0.2', $this->gpm->calculateVersionNumberFromDependencyVersion('>=2.0.2'));
        $this->assertSame('2.0.2', $this->gpm->calculateVersionNumberFromDependencyVersion('~2.0.2'));
        $this->assertSame('1', $this->gpm->calculateVersionNumberFromDependencyVersion('~1'));
        $this->assertSame(null, $this->gpm->calculateVersionNumberFromDependencyVersion(''));
        $this->assertSame(null, $this->gpm->calculateVersionNumberFromDependencyVersion('*'));
        $this->assertSame('2.0.2', $this->gpm->calculateVersionNumberFromDependencyVersion('2.0.2'));
    }
}