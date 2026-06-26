<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Booking;
use App\Models\HousekeepingTeam;
use App\Enums\RoomStatus;
use Illuminate\Support\Facades\File;

class AiToolsService
{
    /**
     * Get hotel arrival and departure statistics for today
     */
    public function get_hotel_stats(): array
    {
        $arrivals = Booking::arrivingToday()->with('customer')->get();
        $departures = Booking::departingToday()->with('customer')->get();
        $inHouse = Booking::inHouse()->count();
        $availableRooms = Room::where('status', RoomStatus::AVAILABLE)->pluck('number')->toArray();

        return [
            'arrivals_count' => $arrivals->count(),
            'arrivals_names' => $arrivals->map(fn($b) => $b->customer->full_name ?? 'Inconnu')->toArray(),
            'departures_count' => $departures->count(),
            'departures_names' => $departures->map(fn($b) => $b->customer->full_name ?? 'Inconnu')->toArray(),
            'in_house_count' => $inHouse,
            'available_rooms_count' => count($availableRooms),
            'available_rooms_numbers' => $availableRooms,
        ];
    }

    /**
     * Get housekeeping status (teams and dirty/maintenance rooms)
     */
    public function get_housekeeping_status(): array
    {
        $dirtyRooms = Room::where('status', RoomStatus::DIRTY)->pluck('number')->toArray();
        $maintenanceRooms = Room::where('status', RoomStatus::MAINTENANCE)->pluck('number')->toArray();
        
        $teamsData = [];
        if (class_exists(HousekeepingTeam::class)) {
            $teams = HousekeepingTeam::where('is_active', true)->withCount('activeAssignments')->get();
            foreach ($teams as $team) {
                $teamsData[] = [
                    'name' => $team->name,
                    'is_busy' => $team->active_assignments_count > 0,
                    'active_assignments' => $team->active_assignments_count
                ];
            }
        }

        return [
            'dirty_rooms_count' => count($dirtyRooms),
            'dirty_rooms_numbers' => $dirtyRooms,
            'maintenance_rooms_count' => count($maintenanceRooms),
            'maintenance_rooms_numbers' => $maintenanceRooms,
            'teams' => $teamsData,
        ];
    }

    /**
     * Store a fact in the JSON memory file
     */
    public function learn_fact(string $fact): array
    {
        $path = storage_path('app/ai_memory.json');
        $memories = [];
        
        if (File::exists($path)) {
            $memories = json_decode(File::get($path), true) ?? [];
        }

        $memories[] = [
            'date' => now()->toDateTimeString(),
            'fact' => $fact
        ];

        File::put($path, json_encode($memories, JSON_PRETTY_PRINT));

        return ['status' => 'success', 'message' => "Le fait a été mémorisé avec succès."];
    }

    /**
     * Get all memorized facts
     */
    public function get_memories(): array
    {
        $path = storage_path('app/ai_memory.json');
        if (File::exists($path)) {
            return json_decode(File::get($path), true) ?? [];
        }
        return [];
    }
}
