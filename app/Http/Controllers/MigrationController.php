<?php

namespace App\Http\Controllers;

use App\Models\DbMigration;
use App\Services\SubsiteService;
use App\Services\DatabaseService;
use App\Services\MigrateTablesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MigrationController extends Controller
{
    public function index()
    {
        $databases = DatabaseService::getAssociativeDatabaseList();

        return view('migrator', [
            'databases' => $databases,
        ]);
    }

    public function getSubsites(SubsiteService $service, Request $request): JsonResponse
    {
        $database = request('database');
        if (!$database) {
            return response()->json(['error' => 'No Database']);
        }
        DatabaseService::setDb($database);
        $currentUrl = DatabaseService::getInverseDatabaseList()[$database];

        $subsites = $service->getActiveBlogsForSelect();

        return response()->json(['subsites' => $subsites, 'currentUrl' => $currentUrl]);
    }

    public function migration(MigrateTablesService $service, Request $request)
    {
        $databaseFrom = request('databaseFrom');
        $databaseTo = request('databaseTo');
        $from = request('from');

        $results = false;
        if ($databaseFrom && $databaseTo && $from) {
            $results = $service->setBlogToMigrate($from)
                ->setSourceDatabase($databaseFrom)
                ->setDestDatabase($databaseTo)
                ->run();
        }

        return response()->json(['results' => $results]);
    }

    public function getUndoableSubsites(Request $request): JsonResponse
    {
        $destDatabase = request('database_to');
        $subsites = DbMigration::where('destDatabase', $destDatabase)
            ->where('created', 1)
            ->get()
            ->map(function ($dbMigration) {
                if (! $dbMigration->subsiteUrl) {
                    return null;
                }
                return ['blogId' => $dbMigration->destSubsiteId, 'siteurl' => $dbMigration->subsiteUrl];
            })->filter()->toArray();

        return response()->json(['subsites' => $subsites]);
    }

    public function removeSubsite(MigrateTablesService $service, Request $request): JsonResponse
    {
        $destDatabase = request('database_to');
        $subsitedId = request('subsite_id');

        return response()->json(['success' => true]);
    }
}
