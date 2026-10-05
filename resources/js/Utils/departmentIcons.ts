import type { Component } from 'vue';
import {
    Briefcase,
    Building2,
    Calculator,
    ClipboardList,
    Factory,
    Home,
    Landmark,
    Megaphone,
    Monitor,
    Scale,
    Settings,
    ShieldCheck,
    Truck,
    Users,
    Warehouse,
    Wrench,
} from '@lucide/vue';

export interface DepartmentIconOption {
    key: string;
    label: string;
    component: Component;
}

export const departmentIconOptions: DepartmentIconOption[] = [
    { key: 'building-2', label: 'ساختمان', component: Building2 },
    { key: 'landmark', label: 'ستاد', component: Landmark },
    { key: 'briefcase', label: 'کسب‌وکار', component: Briefcase },
    { key: 'users', label: 'منابع انسانی', component: Users },
    { key: 'factory', label: 'تولید', component: Factory },
    { key: 'calculator', label: 'مالی', component: Calculator },
    { key: 'scale', label: 'حقوقی', component: Scale },
    { key: 'monitor', label: 'فناوری', component: Monitor },
    { key: 'wrench', label: 'فنی', component: Wrench },
    { key: 'truck', label: 'لجستیک', component: Truck },
    { key: 'shield-check', label: 'حفاظت', component: ShieldCheck },
    { key: 'clipboard-list', label: 'عملیات', component: ClipboardList },
    { key: 'megaphone', label: 'روابط عمومی', component: Megaphone },
    { key: 'warehouse', label: 'انبار', component: Warehouse },
    { key: 'settings', label: 'تنظیمات', component: Settings },
    { key: 'home', label: 'عمومی', component: Home },
];

export const getDepartmentIcon = (key: string | null | undefined): Component =>
    departmentIconOptions.find((option) => option.key === key)?.component ?? Building2;

export const getDepartmentIconLabel = (key: string | null | undefined): string =>
    departmentIconOptions.find((option) => option.key === key)?.label ?? 'ساختمان';
