import React from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ tenantsCount, totalRevenue, activePlans }: any) {
    return (
        <div className="min-h-screen bg-gray-100">
            <Head title="Super Admin Dashboard" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h1 className="text-2xl font-bold mb-6">Super Admin Dashboard</h1>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                            <div className="p-4 bg-blue-100 rounded-lg">
                                <h2 className="text-lg font-semibold">Total Tenants</h2>
                                <p className="text-3xl">{tenantsCount}</p>
                            </div>
                            <div className="p-4 bg-green-100 rounded-lg">
                                <h2 className="text-lg font-semibold">Total Revenue</h2>
                                <p className="text-3xl">${totalRevenue}</p>
                            </div>
                            <div className="p-4 bg-purple-100 rounded-lg">
                                <h2 className="text-lg font-semibold">Active Plans</h2>
                                <p className="text-3xl">{activePlans}</p>
                            </div>
                        </div>

                        <div>
                            <Link href="/super-admin/settings" className="text-blue-500 hover:underline">
                                Go to Settings
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
