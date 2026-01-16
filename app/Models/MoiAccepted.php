<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoiAccepted extends Model
{
    use HasFactory;

    protected $table = 'moi_accepted_list';

    protected $fillable = [
        'university_id',
        'institute_id',
        'type',
        'created_by'
    ];

    // Relationship to University
    public function university()
    {
        return $this->belongsTo(University::class);
    }

    // Relationship to Institute
    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    // Relationship to Creator
    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Add multiple institutes to a university
     *
     * @param int $universityId
     * @param array $instituteIds
     * @param int $createdBy
     * @return array
     */
    public static function addInstitutesToUniversity(int $universityId, array $instituteIds, int $createdBy,$type): array
    {
        $addedRecords = [];
        $existingRecords = self::where('university_id', $universityId)
            ->whereIn('institute_id', $instituteIds)
            ->pluck('institute_id')
            ->where('type',$type)
            ->toArray();

        $university = University::findOrFail($universityId);
        if($type == 2){
            $university = Homeuniversity::findOrFail($universityId);
        } 
        $instituteNames = Institute::whereIn('id', $instituteIds)->pluck('name', 'id');

        foreach ($instituteIds as $instituteId) {
            if (!in_array($instituteId, $existingRecords)) {
                $record = self::create([
                    'university_id' => $universityId,
                    'type' => $type,
                    'institute_id' => $instituteId,
                    'created_by' => $createdBy
                ]);
                $addedRecords[] = $record;

                // Log activity for each addition
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Institute Added to University',
                        'message' => "Institute '{$instituteNames[$instituteId]}' added to University '{$university->name}'",
                        'university_id' => $universityId,
                        'institute_id' => $instituteId
                    ]),
                    'module_id' => $universityId,
                    'module_type' => 'university',
                    'created_by' => $createdBy,
                    'notification_type' => 'Institute Association Added'
                ]);
            }
        }



        return $addedRecords;
    }

    public static function updateInstitutes(int $universityId, array $newInstituteIds, int $userId): array
    {
        $university = University::findOrFail($universityId);
        $currentAssociations = self::where('university_id', $universityId)->get();
        $currentInstituteIds = $currentAssociations->pluck('institute_id')->toArray();

        $instituteNames = Institute::whereIn('id', $newInstituteIds)
            ->pluck('name', 'id')
            ->toArray();

        $results = [
            'added' => [],
            'removed' => [],
            'unchanged' => []
        ];

        // Identify institutes to add
        $toAdd = array_diff($newInstituteIds, $currentInstituteIds);
        foreach ($toAdd as $instituteId) {
            $record = self::create([
                'university_id' => $universityId,
                'institute_id' => $instituteId,
                'created_by' => $userId
            ]);
            $results['added'][] = $record;

            addLogActivity([
                'type' => 'success',
                'note' => json_encode([
                    'title' => 'MOI University added in '.$university->name,
                    'message' => "Added '{$instituteNames[$instituteId]}' to '{$university->name}'",
                    'action' => 'create',
                    'university_id' => $universityId,
                    'institute_id' => $instituteId
                ]),
                'module_id' => $universityId,
                'module_type' => 'university',
                'created_by' => $userId,
                'notification_type' => 'Institute Association Added'
            ]);
        }

        // Identify institutes to remove
        $toRemove = array_diff($currentInstituteIds, $newInstituteIds);
        foreach ($toRemove as $instituteId) {
            $record = self::where('university_id', $universityId)
                ->where('institute_id', $instituteId)
                ->first();

            if ($record) {
                $instituteName = Institute::find($instituteId)->name ?? 'Unknown';
                $record->delete();
                $results['removed'][] = $instituteId;

                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' =>  'MOI University removed in '.$university->name,
                        'message' => "Removed '{$instituteName}' from '{$university->name}'",
                        'action' => 'delete',
                        'university_id' => $universityId,
                        'institute_id' => $instituteId
                    ]),
                    'module_id' => $universityId,
                    'module_type' => 'university',
                    'created_by' => $userId,
                    'notification_type' => 'Institute Association Removed'
                ]);
            }
        }

        // Identify unchanged institutes
        $results['unchanged'] = array_intersect($currentInstituteIds, $newInstituteIds);

        // // Log summary
        // addLogActivity([
        //     'type' => 'success',
        //     'note' => json_encode([
        //         'title' => 'Institute Associations Updated',
        //         'message' => "Updated associations for '{$university->name}'",
        //         'action' => 'update',
        //         'summary' => [
        //             'added' => count($toAdd),
        //             'removed' => count($toRemove),
        //             'unchanged' => count($results['unchanged'])
        //         ],
        //         'university_id' => $universityId,
        //         'details' => [
        //             'added' => array_values($toAdd),
        //             'removed' => array_values($toRemove),
        //             'unchanged' => $results['unchanged']
        //         ]
        //     ]),
        //     'module_id' => $universityId,
        //     'module_type' => 'university',
        //     'created_by' => $userId,
        //     'notification_type' => 'Institute Associations Updated'
        // ]);

        return $results;
    }
}
