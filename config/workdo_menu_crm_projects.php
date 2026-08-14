<?php

return [
    ['module' => 'Calendar', 'menu_type' => 'company-menu', 'title' => 'Calendar', 'route' => 'calendar.view.index', 'permission' => 'manage-calendar', 'group' => 'Project Management', 'order' => 930],
    ['module' => 'FormBuilder', 'menu_type' => 'company-menu', 'title' => 'Form Builder', 'route' => 'formbuilder.forms.index', 'permission' => 'manage-formbuilder', 'group' => 'Project Management', 'order' => 510],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'CRM Dashboard', 'route' => 'lead.index', 'permission' => 'manage-crm-dashboard', 'parent' => 'dashboard', 'order' => 50],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'CRM', 'route' => 'lead.leads.index', 'permission' => 'manage-leads', 'group' => 'Sales & Revenue', 'order' => 500],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'Leads', 'route' => 'lead.leads.index', 'permission' => 'manage-leads'],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'Deals', 'route' => 'lead.deals.index', 'permission' => 'manage-deals'],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'Reports', 'route' => 'lead.reports.index', 'permission' => 'view-reports'],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'Lead Reports', 'route' => 'lead.reports.leads', 'permission' => 'view-reports'],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'Deal Reports', 'route' => 'lead.reports.deals', 'permission' => 'view-reports'],
    ['module' => 'Lead', 'menu_type' => 'company-menu', 'title' => 'System Setup', 'route' => 'lead.pipelines.index', 'permission' => 'manage-pipelines'],
    ['module' => 'Taskly', 'menu_type' => 'company-menu', 'title' => 'Project Dashboard', 'route' => 'project.dashboard.index', 'permission' => 'manage-project-dashboard', 'parent' => 'dashboard', 'order' => 20],
    ['module' => 'Taskly', 'menu_type' => 'company-menu', 'title' => 'Project', 'route' => 'project.index', 'permission' => 'manage-project', 'group' => 'Project Management', 'order' => 300],
    ['module' => 'Taskly', 'menu_type' => 'company-menu', 'title' => 'Projects', 'route' => 'project.index', 'permission' => 'manage-project', 'order' => 5],
    ['module' => 'Taskly', 'menu_type' => 'company-menu', 'title' => 'Projects Report', 'route' => 'project.report.index', 'permission' => 'manage-project-report', 'order' => 10],
    ['module' => 'Taskly', 'menu_type' => 'company-menu', 'title' => 'System Setup', 'route' => 'project.task-stages.index', 'permission' => 'manage-task-stages', 'order' => 20],
    ['module' => 'Timesheet', 'menu_type' => 'company-menu', 'title' => 'Timesheet', 'route' => 'timesheet.index', 'permission' => 'manage-timesheet', 'group' => 'Project Management', 'order' => 1450],
];
