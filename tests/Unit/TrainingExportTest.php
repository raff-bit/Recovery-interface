<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bll/TrainingExport.php';
require_once __DIR__ . '/../Support/ZipReader.php';

/**
 * TrainingExport — what goes in each row of the Excel export.
 */
final class TrainingExportTest extends TestCase
{
    private const WORKOUTS = [
        ['id' => 1, 'workout_date' => '2026-07-01 17:30:00', 'label' => 'Push', 'notes' => 'felt strong'],
        ['id' => 2, 'workout_date' => '2026-07-03 09:00:00', 'label' => null,   'notes' => null],
    ];

    private const SETS = [
        1 => [
            ['workout_id' => 1, 'exercise_id' => 1, 'exercise_name' => 'Bench Press', 'set_number' => 1, 'reps' => 8, 'weight_kg' => '60.00', 'rpe' => '8.0'],
            ['workout_id' => 1, 'exercise_id' => 1, 'exercise_name' => 'Bench Press', 'set_number' => 2, 'reps' => 8, 'weight_kg' => '62.50', 'rpe' => null],
            ['workout_id' => 1, 'exercise_id' => 7, 'exercise_name' => 'Pull-ups',    'set_number' => 1, 'reps' => 10, 'weight_kg' => '0.00', 'rpe' => '9.0'],
        ],
    ];

    public function testTimezoneMustBeARealIanaZone(): void
    {
        $this->assertSame('Europe/Lisbon', TrainingExport::resolveTimezone('Europe/Lisbon')->getName());
        foreach ([null, '', 'Mars/Olympus', '+01:00', '../../etc', ['Europe/Lisbon'], 42] as $bad) {
            $this->assertSame('UTC', TrainingExport::resolveTimezone($bad)->getName(), var_export($bad, true));
        }
    }

    public function testStoredUtcIsShownInTheUsersZone(): void
    {
        // 17:30 UTC in July = 18:30 in Lisbon (summer time) = 13:30 in New York
        $this->assertSame('2026-07-01 18:30', TrainingExport::toLocal('2026-07-01 17:30:00', new DateTimeZone('Europe/Lisbon'))->format('Y-m-d H:i'));
        $this->assertSame('2026-07-01 13:30', TrainingExport::toLocal('2026-07-01 17:30:00', new DateTimeZone('America/New_York'))->format('Y-m-d H:i'));
    }

    public function testSessionRowsSummariseEachWorkout(): void
    {
        $rows = TrainingExport::sessionRows(self::WORKOUTS, self::SETS, new DateTimeZone('Europe/Lisbon'));

        $this->assertCount(2, $rows);
        $this->assertSame('2026-07-01 18:30', $rows[0][0]->format('Y-m-d H:i'));
        // label, distinct exercises, sets, volume = 8×60 + 8×62.5 + 10×0, notes
        $this->assertSame(['Push', 2, 3, 980.0, 'felt strong'], array_slice($rows[0], 1));
        // a workout with no sets still gets its row, with zeros
        $this->assertSame([null, 0, 0, 0.0, null], array_slice($rows[1], 1));
    }

    public function testSetRowsHaveOneLinePerSetWithTypedValues(): void
    {
        $rows = TrainingExport::setRows(self::WORKOUTS, self::SETS, new DateTimeZone('UTC'));

        $this->assertCount(3, $rows);
        $this->assertSame(['Push', 'Bench Press', 2, 8, 62.5, null, 500.0], array_slice($rows[1], 1)); // empty RPE stays empty
        $this->assertSame(['Push', 'Pull-ups', 1, 10, 0.0, 9.0, 0.0], array_slice($rows[2], 1));
    }

    public function testWorkbookHasSessionsAndSetsSheets(): void
    {
        $files = ZipReader::read(TrainingExport::toXlsx(self::WORKOUTS, self::SETS, new DateTimeZone('UTC')));

        $this->assertStringContainsString('<sheet name="Sessions"', $files['xl/workbook.xml']);
        $this->assertStringContainsString('<sheet name="Sets"', $files['xl/workbook.xml']);
        $this->assertStringContainsString('<dimension ref="A1:F3"/>', $files['xl/worksheets/sheet1.xml']); // header + 2 workouts
        $this->assertStringContainsString('<dimension ref="A1:H4"/>', $files['xl/worksheets/sheet2.xml']); // header + 3 sets
    }

    public function testNoWorkoutsGivesAValidWorkbookWithJustHeaders(): void
    {
        $files = ZipReader::read(TrainingExport::toXlsx([], [], new DateTimeZone('UTC')));
        $this->assertStringContainsString('<dimension ref="A1:F1"/>', $files['xl/worksheets/sheet1.xml']);
    }
}
