<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Sync\Domain\Services\SyncService;

class RecoverStuckSyncJobsCommand extends Command
{
    protected $signature = 'sync:recover {--branch_id= : ID de la sucursal a recuperar / 要恢复的分支机构ID}';
    protected $description = 'Recupera trabajos de sincronización atrapados en estado processing cuyo lease haya expirado / 恢复卡在处理状态且租约已过期的同步作业';

    public function __construct(
        private SyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $branchId = $this->option('branch_id');

        if ($branchId) {
            $this->recoverBranch((int) $branchId);
        } else {
            $branches = Branch::all();
            foreach ($branches as $branch) {
                $this->recoverBranch($branch->id);
            }
        }

        return Command::SUCCESS;
    }

    private function recoverBranch(int $branchId): void
    {
        $this->info("Recuperando trabajos atrapados para la sucursal {$branchId}... / 正在恢复分支 {$branchId} 的卡住作业...");
        
        $result = $this->syncService->recoverStuckJobs($branchId);
        
        if ($result['recovered'] > 0) {
            $this->info("✅ {$result['recovered']} trabajo(s) recuperado(s) exitosamente. / ✅ 成功恢复 {$result['recovered']} 个作业。");
        } else {
            $this->info("ℹ️ No se encontraron trabajos atrapados. / ℹ️ 未找到卡住的作业。");
        }

        if (!empty($result['errors'])) {
            $this->error("❌ Errores durante la recuperación / 恢复过程中出错:");
            foreach ($result['errors'] as $error) {
                $this->error("  - Job {$error['queue_id']}: {$error['error']}");
            }
        }
    }
}
