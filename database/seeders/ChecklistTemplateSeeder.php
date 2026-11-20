<?php

namespace Database\Seeders;

use App\Enums\AnswerType;
use App\Models\ChecklistAnswerSet;
use App\Models\ChecklistSection;
use App\Services\Checklist\ChecklistService;
use Illuminate\Database\Seeder;

/**
 * The starting inspection checklist, based on the CarChecks sample report.
 * Safe to run again: only missing sections and items are added, so admin
 * edits (renames aside) and hidden items are left alone.
 *
 * php artisan db:seed --class=ChecklistTemplateSeeder
 */
class ChecklistTemplateSeeder extends Seeder
{
    private const COUNT = ['answer_type' => 'number', 'min_value' => 0];

    private const TYRE_WEAR = ['answer_type' => 'percentage', 'problem_above' => 80];

    private const TEXT = ['answer_type' => 'text'];

    /**
     * Section name => [item label => answer list name, or the item's answer fields].
     *
     * @var array<string, array<string, string|array<string, mixed>>>
     */
    public const SECTIONS = [
        'Basics and Tyres' => [
            'No. of Doors' => self::COUNT,
            'No. of Automatic Doors' => self::COUNT,
            'No. of Seats' => self::COUNT,
            'No. of Airbags' => self::COUNT,
            'ODO Reading' => ['answer_type' => 'number', 'unit' => 'km', 'min_value' => 0],
            'Roof' => 'Roof',
            'Roof Mechanism' => 'Roof mechanism',
            'Wheel Type' => 'Wheel type',
            'Drive System' => 'Drive system',
            'Accidents' => 'Accidents',
            'Alterations' => 'Presence',
            'Extras' => self::TEXT,
            'Front Left Tyre Wear' => self::TYRE_WEAR,
            'Front Right Tyre Wear' => self::TYRE_WEAR,
            'Rear Left Tyre Wear' => self::TYRE_WEAR,
            'Rear Right Tyre Wear' => self::TYRE_WEAR,
        ],
        'Structure' => [
            'A Pillar' => 'Condition',
            'B Pillar' => 'Condition',
            'C Pillar' => 'Condition',
            'D Pillar' => 'Condition',
            'Chassis Legs & Box Jt Front' => 'Condition',
            'Chassis Legs & Box Jt Center' => 'Condition',
            'Chassis Legs & Box Jt Rear' => 'Condition',
            'Front Subframe' => 'Condition',
        ],
        'Engine, Hybrid & Electric System' => [
            'Camshaft Sensor' => 'Condition',
            'Crankshaft Sensor' => 'Condition',
            'Hybrid System' => 'Condition',
            'Turbo' => 'Condition',
            'Supercharger' => 'Condition',
            'Throttle Body' => 'Condition',
            'Distributor' => 'Condition',
            'High Tension Leads' => 'Condition',
            'Igniter Coils' => 'Condition',
            'Engine Misfiring' => 'Fault present',
            'Tappet Cover' => 'Condition',
            'Tappet Cover Oil Seal' => 'Condition',
            'Tappets' => 'Tappets',
            'Top Engine Mounts' => 'Condition',
            'Air Filter' => 'Condition',
            'Air Flow Sensor' => 'Condition',
            'Induction Housing' => 'Condition',
            'Camshaft Oil Seal' => 'Condition',
            'Crankshaft Oil Seal' => 'Condition',
            'Timing Chain' => 'Condition',
            'Timing Belt' => 'Condition',
            'Alternator' => 'Condition',
            'Alternator Belt' => 'Condition',
            'Drive Belt Pulleys' => 'Condition',
            'Sump Gasket' => 'Condition',
            'Rear Drive Shaft Oil Seals' => 'Condition',
            'Engine Varnish' => 'Presence',
            'Engine Sludge' => 'Presence',
            'Engine Blow-By' => 'Presence',
            'Burning Oil' => 'Fault present',
            'Oil Leaks' => 'Fault present',
            'Abnormal Noises (recommend inspection)' => 'Fault present',
        ],
        'Braking System' => [
            'ABS' => 'Condition',
            'Master Pump' => 'Condition',
            'Parking Brake' => 'Condition',
            'Brake Fluid' => 'Condition',
            'Brake Oil Reservoir' => 'Condition',
            'Reservoir Cap' => 'Condition',
            'Brake Servo' => 'Condition',
            'Front Brake Disc' => 'Condition',
            'Front Brake Pads' => 'Condition',
            'Front Brake Drums/Pistons' => 'Condition',
            'Front Brake Caliper Pins' => 'Condition',
            'Front Brake Caliper Washers' => 'Condition',
            'Front Brake Hoses' => 'Condition',
            'Rear Brake Disc' => 'Condition',
            'Rear Brake Pads' => 'Condition',
            'Rear Brake Drums/Pistons' => 'Condition',
            'Rear Brake Caliper Pins' => 'Condition',
            'Rear Brake Caliper Washers' => 'Condition',
            'Rear Brake Hoses' => 'Condition',
        ],
        'Transmission System' => [
            'Gearbox Oil' => 'Condition',
            'Gearbox Mounts' => 'Condition',
            'Gear Selection' => 'Condition',
            'Gear Engagement' => 'Gear engagement',
            'Gear Bushing' => 'Condition',
            'Gearbox Shafts/Cables' => 'Condition',
            'Gearbox Oil Seals' => 'Condition',
            'Gearbox Rasers' => 'Condition',
            'Clutch Pump' => 'Condition',
            'Clutch Pump Fluid' => 'Condition',
            'Clutch Cable' => 'Condition',
            'Clutch Plate' => 'Condition',
            'Pressure Plate' => 'Condition',
            'Release Bearing' => 'Condition',
            'Traction Control' => 'Condition',
            'Stability Control' => 'Condition',
            'Tip-Tronic System' => 'Condition',
            'Economy Mode' => 'Condition',
            'Sport Mode' => 'Condition',
            'Propeller Shaft Dampers' => 'Condition',
            'Universal Joint' => 'Condition',
            'Center Bearing' => 'Condition',
            'Front Inner CV Joints & Boots' => 'Condition',
            'Front Outer CV Joints & Boots' => 'Condition',
            'Front Drive Shafts' => 'Condition',
            'Front Drive Shaft Oil Seals' => 'Condition',
            'Rear Inner CV Joints & Boots' => 'Condition',
            'Rear Outer CV Joints & Boots' => 'Condition',
            'Rear Drive Shafts' => 'Condition',
        ],
        'Suspension' => [
            'Front Differential' => 'Condition',
            'Rear Differential' => 'Condition',
            'Front Strut Brace' => 'Condition',
            'Rear Strut Brace' => 'Condition',
            'Front Left Shock Absorbers' => 'Condition',
            'Front Right Shock Absorbers' => 'Condition',
            'Rear Left Shock Absorbers' => 'Condition',
            'Rear Right Shock Absorbers' => 'Condition',
            'Front Shock Absorber Mounts' => 'Condition',
            'Front Shock Absorber Bumpers' => 'Condition',
            'Front Shock Absorber Bushes' => 'Condition',
            'Rear Shock Absorber Mounts' => 'Condition',
            'Rear Shock Absorber Bumpers' => 'Condition',
            'Rear Shock Absorber Bushes' => 'Condition',
            'Front Coil Springs' => 'Condition',
            'Front Coil Spring Rubber Dampers' => 'Condition',
            'Rear Coil Springs' => 'Condition',
            'Rear Coil Spring Rubber Damping' => 'Condition',
            'Front Upper Arm Bushes' => 'Condition',
            'Front Upper Swing Arm Bushes' => 'Condition',
            'Front Lower Swing Arms' => 'Condition',
            'Front Lower Arm Bushes' => 'Condition',
            'Front Control Arms & Bushing' => 'Condition',
            'Rear Upper Arm Bushes' => 'Condition',
            'Rear Upper Swing Arm Bushes' => 'Condition',
            'Rear Lower Swing Arm' => 'Condition',
            'Rear Lower Arm Bushes' => 'Condition',
            'Front Stabilizer Bar' => 'Condition',
            'Front Stabilizer Bar Bushes' => 'Condition',
            'Front Stabilizer Bar Multi Links' => 'Condition',
            'Rear Stabilizer Bar' => 'Condition',
            'Rear Stabilizer Bar Bushes' => 'Condition',
            'Front Leaf Springs' => 'Condition',
            'Front Leaf Spring Bush' => 'Condition',
            'Front Leaf Spring Shackles' => 'Condition',
            'Front Leaf Spring Rebound Clips' => 'Condition',
            'Rear Leaf Springs' => 'Condition',
            'Rear Leaf Spring Bush' => 'Condition',
            'Rear Leaf Spring Shackles' => 'Condition',
            'Rear Leaf Spring Rebound Clips' => 'Condition',
        ],
        'Steering System' => [
            'Wheel Condition' => 'Condition',
            'Power Steering' => 'Condition',
            'Power Steering Pump' => 'Condition',
            'Power Steering Hoses' => 'Condition',
            'Steering Box' => 'Condition',
            'Electric Power Steering Rack' => 'Working',
            'Hydraulic Power Steering Rack' => 'Condition',
            'Rack Ends Left' => 'Condition',
            'Rack Ends Right' => 'Condition',
            'Tie Rod Left' => 'Condition',
            'Tie Rod Right' => 'Condition',
            'Upper Ball Joints' => 'Condition',
            'Lower Ball Joints' => 'Condition',
            'Control Arms' => 'Condition',
        ],
        'Cooling System' => [
            'Radiator Top Tank' => 'Condition',
            'Radiator Bottom Tank' => 'Condition',
            'Radiator Cap' => 'Condition',
            'Radiator Core' => 'Condition',
            'Radiator Fan' => 'Working',
            'Coolant Expansion Tank' => 'Condition',
            'Coolant Lines' => 'Condition',
            'Thermostat/Thermostat Housing' => 'Condition',
            'Water Pump' => 'Condition',
        ],
        'Exhaust System' => [
            'Exhaust Manifold' => 'Condition',
            'Exhaust Manifold Gasket' => 'Condition',
            'Connecting Pipe' => 'Condition',
            'Catalytic Converter' => 'Condition',
            'Center Pipe' => 'Condition',
            'Resonator' => 'Condition',
            'Silencer' => 'Condition',
            'Hangers' => 'Condition',
        ],
        'Interior' => [
            'Interior Type' => 'Interior type',
            'Interior Condition' => 'Rating',
            'Interior Torn' => 'Fault present',
            'Interior Trim' => 'Condition',
            'Interior Lights' => 'Working',
            'Seat Type' => 'Seat type',
            'Seat Covers Fitted' => 'Yes / No',
            'Seat Control Mechanism' => 'Working',
            'No. of Seat Belts' => self::COUNT,
            'Seat Belt Condition' => 'Working',
            'Dashboard Condition' => 'Condition',
            'Dashboard Components' => 'Condition',
            'Meters' => 'Working',
            'Airbags' => 'Condition',
            'Horn' => 'Working',
            'Rear View Mirror' => 'Condition',
            'Side Mirror Type' => 'Side mirror type',
            'Power Shutters' => 'Condition',
            'Central Locking' => 'Condition',
            'Air Conditioning' => 'Condition',
            'Rear Air Conditioning' => 'Condition',
            'Accelerator Pedal Rubber' => 'Condition',
            'Brake Pedal Rubber' => 'Condition',
            'Clutch Pedal Rubber' => 'Condition',
            'Self Starter' => 'Condition',
        ],
        'Exterior' => [
            'Windscreen' => 'Condition',
            'Windscreen Wiper Blades & Arms' => 'Condition',
            'Windscreen Washer Nozzles' => 'Working',
            'Wiper Mechanism' => 'Working',
            'Rear Windscreen' => 'Condition',
            'Rear Windscreen Wiper Blades & Arms' => 'Condition',
            'Rear Windscreen Washer Nozzles' => 'Working',
            'Rear Wiper Mechanism' => 'Working',
            'Side Mirrors' => 'Condition',
            'Side Mirror Condition' => 'Working',
            'Reverse Camera' => 'Condition',
            '360 Camera' => 'Condition',
            'Parking Sensors Front' => 'Condition',
            'Parking Sensors Rear' => 'Condition',
            'Head Lights' => 'Condition',
            'Fog Lights' => 'Condition',
            'Parking Lights' => 'Condition',
            'Turn Signal Lights' => 'Condition',
            'Hazard Warning Lights' => 'Condition',
            'Brake Lights' => 'Condition',
            'Reverse Lights' => 'Condition',
        ],
    ];

    public function __construct(private ChecklistService $checklist) {}

    public function run(): void
    {
        $this->call(ChecklistAnswerSetSeeder::class);

        $listIds = ChecklistAnswerSet::pluck('id', 'name');

        foreach (self::SECTIONS as $sectionName => $items) {
            $section = ChecklistSection::where('name', $sectionName)->first()
                ?? $this->checklist->createSection(['name' => $sectionName]);

            $existing = $section->items()->pluck('label')->map(fn (string $label) => mb_strtolower($label))->all();

            foreach ($items as $label => $answer) {
                if (in_array(mb_strtolower($label), $existing, true)) {
                    continue;
                }

                $fields = is_string($answer)
                    ? ['answer_type' => AnswerType::Choice->value, 'checklist_answer_set_id' => $listIds[$answer]]
                    : $answer;

                $this->checklist->createItem($section, ['label' => $label, ...$fields]);
            }
        }
    }
}
