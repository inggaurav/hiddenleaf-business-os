import React from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function SettingsIndex({ settings }: any) {
    const { data, setData, post, processing, errors } = useForm({
        site_name: settings.site_name || '',
        default_currency: settings.default_currency || 'USD',
        timezone: settings.timezone || 'UTC',
        theme_color: settings.theme_color || '#000000',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/super-admin/settings');
    };

    return (
        <div className="min-h-screen bg-gray-100">
            <Head title="Global Settings" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h1 className="text-2xl font-bold mb-6">Global Settings</h1>

                        <form onSubmit={submit} className="space-y-4 max-w-lg">
                            <div>
                                <label className="block text-sm font-medium">Site Name</label>
                                <input
                                    type="text"
                                    value={data.site_name}
                                    onChange={(e) => setData('site_name', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                />
                                {errors.site_name && <p className="text-red-500 text-sm">{errors.site_name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium">Default Currency</label>
                                <input
                                    type="text"
                                    value={data.default_currency}
                                    onChange={(e) => setData('default_currency', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium">Timezone</label>
                                <input
                                    type="text"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium">Theme Color</label>
                                <input
                                    type="color"
                                    value={data.theme_color}
                                    onChange={(e) => setData('theme_color', e.target.value)}
                                    className="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm p-1"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-blue-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600"
                            >
                                Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
