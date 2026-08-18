<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->nullableMorphs('taskable');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('retainer_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
        
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('retainer_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });

        // Data Migration
        // Move existing tasks to use polymorphic relation
        DB::table('tasks')->whereNotNull('project_id')->update([
            'taskable_type' => 'App\Models\Project',
            'taskable_id' => DB::raw('project_id')
        ]);
        
        // Let's migrate the relations for the retainer we just created.
        // We know old_project_id = 15, new_retainer_id = 1.
        // For production, a more generic approach would map this from a table, but since this is our specific database state:
        $retainers = DB::table('retainers')->get();
        foreach($retainers as $ret) {
            // We stored the old project ID in terms: "Migrated from project ID: 15"
            if (preg_match('/Migrated from project ID: (\d+)/', $ret->terms, $matches)) {
                $old_project_id = $matches[1];
                
                // Update Invoices
                DB::table('invoices')
                  ->where('project_id', $old_project_id)
                  ->update(['project_id' => null, 'retainer_id' => $ret->id]);
                  
                // Update Tasks
                DB::table('tasks')
                  ->where('taskable_type', 'App\Models\Project')
                  ->where('taskable_id', $old_project_id)
                  ->update(['taskable_type' => 'App\Models\Retainer', 'taskable_id' => $ret->id]);
                  
                // Update Expenses
                DB::table('expenses')
                  ->where('project_id', $old_project_id)
                  ->update(['project_id' => null, 'retainer_id' => $ret->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropMorphs('taskable');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['retainer_id']);
            $table->dropColumn('retainer_id');
        });
        
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['retainer_id']);
            $table->dropColumn('retainer_id');
        });
    }
};
