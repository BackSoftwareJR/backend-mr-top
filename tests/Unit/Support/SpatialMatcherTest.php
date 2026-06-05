<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SpatialMatcher;
use PHPUnit\Framework\TestCase;

class SpatialMatcherTest extends TestCase
{
    private SpatialMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new SpatialMatcher;
    }

    public function test_circles_overlap_when_distance_less_than_sum_of_radii(): void
    {
        $this->assertTrue($this->matcher->circlesOverlap(45.4642, 9.19, 15, 45.47, 9.22, 15));
    }

    public function test_circles_do_not_overlap_when_far_apart(): void
    {
        $this->assertFalse($this->matcher->circlesOverlap(45.4642, 9.19, 10, 41.9028, 12.4964, 10));
    }

    public function test_point_in_polygon_detects_inside_point(): void
    {
        $ring = [
            [9.0, 45.0],
            [9.5, 45.0],
            [9.5, 45.5],
            [9.0, 45.5],
            [9.0, 45.0],
        ];

        $this->assertTrue($this->matcher->pointInPolygon(45.25, 9.25, $ring));
        $this->assertFalse($this->matcher->pointInPolygon(46.0, 10.0, $ring));
    }

    public function test_circle_intersects_polygon_when_center_inside(): void
    {
        $ring = [
            [9.0, 45.0],
            [9.5, 45.0],
            [9.5, 45.5],
            [9.0, 45.5],
            [9.0, 45.0],
        ];

        $this->assertTrue($this->matcher->circleIntersectsPolygon(45.25, 9.25, 5, $ring));
    }
}
