<?php

namespace App\Traits;

use App\Models\{Order, User};

trait OrderStatusTrait
{
    protected function applyRoleAwareStatusFilterToQuery($query, string $status, ?User $viewer): void
    {
        $normalizedStatus = strtolower($status);

        if ($viewer?->role === 'user') {
            if ($normalizedStatus === 'pending') {
                $query->where(function ($pendingQuery) {
                    $pendingQuery->where('status', 'new')
                        ->orWhere(function ($assignedQuery) {
                            $assignedQuery->where('status', 'pending')
                                ->where(function ($runnerStatusQuery) {
                                    $runnerStatusQuery->whereNull('runner_status')
                                        ->orWhere('runner_status', 'pending');
                                });
                        });
                });

                return;
            }

            if ($normalizedStatus === 'ongoing') {
                $query->where('status', 'pending')
                    ->where('runner_status', 'assigned');

                return;
            }

            $query->where('status', $normalizedStatus);

            return;
        }

        if ($viewer?->role === 'runner') {
            $runnerId = $viewer->runner?->id;

            if (! $runnerId) {
                $query->whereNull('id');

                return;
            }

            $query->where('runner_id', $runnerId);

            if ($normalizedStatus === 'new') {
                $query->where('status', 'pending')
                    ->where('runner_status', 'pending');

                return;
            }

            if ($normalizedStatus === 'ongoing') {
                $query->where('status', 'pending')
                    ->where('runner_status', 'assigned');

                return;
            }

            $query->where('status', $normalizedStatus);

            return;
        }

        $query->where('status', $normalizedStatus);
    }

    protected function applyRoleAwareStatusToOrder(Order $order, ?User $viewer): Order
    {
        $order->setAttribute('status', $this->resolveRoleAwareOrderStatus($order, $viewer));

        return $order;
    }

    protected function resolveRoleAwareOrderStatus(Order $order, ?User $viewer): string
    {
        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            return $order->status;
        }

        if ($viewer?->role === 'user') {
            if ($order->status === 'new') {
                return 'pending';
            }

            if ($order->status === 'pending' && $order->runner_status === 'assigned') {
                return 'ongoing';
            }

            return 'pending';
        }

        if ($viewer?->role === 'runner' && $this->isOrderAssignedToRunner($order, $viewer)) {
            if ($order->runner_status === 'pending') {
                return 'new';
            }

            if ($order->runner_status === 'assigned') {
                return 'ongoing';
            }
        }

        return $order->status;
    }

    protected function isOrderAssignedToRunner(Order $order, User $viewer): bool
    {
        if ($viewer->role !== 'runner') {
            return false;
        }

        $runnerId = $viewer->runner?->id;

        if (! $runnerId) {
            return false;
        }

        return (int) $order->runner_id === (int) $runnerId;
    }
}
