import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, router } from '@inertiajs/react';

export default function Index({ modules }) {
    const handleToggle = (moduleName, active) => {
        router.post(route('modules.toggle'), {
            module_name: moduleName,
            active: active
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Modules</h2>}
        >
            <Head title="Modules" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <table className="w-full text-left table-auto min-w-max">
                            <thead>
                                <tr>
                                    <th className="p-4 border-b border-blue-gray-100 bg-blue-gray-50">Name</th>
                                    <th className="p-4 border-b border-blue-gray-100 bg-blue-gray-50">Description</th>
                                    <th className="p-4 border-b border-blue-gray-100 bg-blue-gray-50">Status</th>
                                    <th className="p-4 border-b border-blue-gray-100 bg-blue-gray-50">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {modules.map((module) => (
                                    <tr key={module.name}>
                                        <td className="p-4 border-b border-blue-gray-50">{module.name}</td>
                                        <td className="p-4 border-b border-blue-gray-50">{module.description}</td>
                                        <td className="p-4 border-b border-blue-gray-50">
                                            {module.active ? 'Active' : 'Inactive'}
                                        </td>
                                        <td className="p-4 border-b border-blue-gray-50">
                                            <button
                                                onClick={() => handleToggle(module.name, !module.active)}
                                                className={`px-4 py-2 rounded text-white ${module.active ? 'bg-red-500' : 'bg-green-500'}`}
                                            >
                                                {module.active ? 'Disable' : 'Enable'}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
