<?php

namespace Timatic\Nmbrs\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Timatic\Nmbrs\DataTransferObjects\NmbrsLeaveRequest;

class GetLeaveRequestsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $companyId,
        private readonly int $year,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/companies/'.$this->companyId.'/employees/leaverequests';
    }

    protected function defaultQuery(): array
    {
        return [
            'status' => 'Approved',
            'type' => 'Withdrawal',
            'year' => $this->year,
            'pageSize' => 100,
        ];
    }

    /** @return Collection<int, NmbrsLeaveRequest> */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        $leaveRequests = collect();

        foreach ($data as $employeeData) {
            if (! is_array($employeeData) || ! isset($employeeData['employeeId'])) {
                continue;
            }

            $employeeId = (string) $employeeData['employeeId'];

            foreach ($employeeData['EmployeeLeaveRequests'] ?? [] as $leaveRequest) {
                if (! is_array($leaveRequest) || empty($leaveRequest['startDate']) || empty($leaveRequest['endDate'])) {
                    continue;
                }

                $leaveRequests->push(new NmbrsLeaveRequest(
                    leaveRequestId: (string) ($leaveRequest['leaveRequestsId'] ?? ''),
                    employeeId: $employeeId,
                    startDate: CarbonImmutable::parse($leaveRequest['startDate'], 'Europe/Amsterdam'),
                    endDate: CarbonImmutable::parse($leaveRequest['endDate'], 'Europe/Amsterdam'),
                    hours: (float) ($leaveRequest['hours'] ?? 0),
                ));
            }
        }

        return $leaveRequests;
    }
}
