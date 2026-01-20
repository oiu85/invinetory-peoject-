<?php

namespace Tests\Unit\Services\Spatial;

use App\Services\Spatial\RotationHandler;
use PHPUnit\Framework\TestCase;

class RotationHandlerTest extends TestCase
{
    private RotationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new RotationHandler();
    }

    public function test_get_rotated_dimensions_0_degrees(): void
    {
        $result = $this->handler->getRotatedDimensions(50, 30, 20, RotationHandler::ROTATION_0);

        $this->assertEquals(50, $result['width']);
        $this->assertEquals(30, $result['depth']);
        $this->assertEquals(20, $result['height']);
    }

    public function test_get_rotated_dimensions_90_degrees(): void
    {
        $result = $this->handler->getRotatedDimensions(50, 30, 20, RotationHandler::ROTATION_90);

        $this->assertEquals(30, $result['width']);
        $this->assertEquals(50, $result['depth']);
        $this->assertEquals(20, $result['height']);
    }

    public function test_get_best_rotation_fits(): void
    {
        $rotation = $this->handler->getBestRotation(50, 30, 20, 60, 40, 25, true);

        $this->assertNotNull($rotation);
        $this->assertContains($rotation, RotationHandler::ALL_ROTATIONS);
    }

    public function test_get_best_rotation_does_not_fit(): void
    {
        $rotation = $this->handler->getBestRotation(100, 100, 100, 50, 50, 50, true);

        $this->assertNull($rotation);
    }
}
