<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ReportExport implements FromArray, WithHeadings, ShouldAutoSize
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];

        // عنوان التقرير
        $rows[] = ['تقارير النظام - ' . now()->format('Y-m-d H:i:s')];
        $rows[] = [];

        // ===================== المستخدمين =====================
        $rows[] = ['📊 إحصائيات المستخدمين'];
        $rows[] = ['إجمالي المستخدمين', $this->data['users']['total_users'] ?? 0];
        $rows[] = ['المستخدمين النشطين', $this->data['users']['active_users'] ?? 0];
        $rows[] = ['المستخدمين المحظورين', $this->data['users']['blocked_users'] ?? 0];
        $rows[] = ['نسبة النشاط', ($this->data['users']['active_percentage'] ?? 0) . '%'];
        $rows[] = [];

        // ===================== الخدمات =====================
        $rows[] = ['📊 إحصائيات الخدمات'];
        $rows[] = ['إجمالي الخدمات', $this->data['servings']['total_servings'] ?? 0];
        $rows[] = ['خدمات تطوعية', $this->data['servings']['voluntary_servings'] ?? 0];
        $rows[] = ['خدمات مدفوعة', $this->data['servings']['paid_servings'] ?? 0];
        $rows[] = ['خدمات تبادلية', $this->data['servings']['exchange_servings'] ?? 0];
        $rows[] = [];

        // ===================== الشكاوي =====================
        $rows[] = ['📊 إحصائيات الشكاوي'];
        $rows[] = ['إجمالي الشكاوي', $this->data['complaints']['total_complaints'] ?? 0];
        $rows[] = ['قيد الانتظار', $this->data['complaints']['pending'] ?? 0];
        $rows[] = ['بانتظار الوثائق', $this->data['complaints']['awaiting_documents'] ?? 0];
        $rows[] = ['قيد المراجعة', $this->data['complaints']['under_review'] ?? 0];
        $rows[] = ['تم الحل', $this->data['complaints']['resolved'] ?? 0];
        $rows[] = ['مرفوضة', $this->data['complaints']['rejected'] ?? 0];
        $rows[] = [];

        // ===================== الشكاوي الأسبوعية =====================
        $rows[] = ['📊 الشكاوي الأسبوعية'];
        $rows[] = ['الفترة', $this->data['weekly_complaints']['period'] ?? ''];
        $rows[] = ['عدد الشكاوي', $this->data['weekly_complaints']['total'] ?? 0];
        $rows[] = [];

        // ===================== الشكاوي الشهرية =====================
        $rows[] = ['📊 الشكاوي الشهرية'];
        $rows[] = ['الفترة', $this->data['monthly_complaints']['period'] ?? ''];
        $rows[] = ['عدد الشكاوي', $this->data['monthly_complaints']['total'] ?? 0];
        $rows[] = [];

        // ===================== تذييل =====================
        $rows[] = [];
        $rows[] = ['تم إنشاء التقرير في: ' . now()->format('Y-m-d H:i:s')];

        return $rows;
    }

    public function headings(): array
    {
        return [
            'المعيار',
            'القيمة',
        ];
    }
}