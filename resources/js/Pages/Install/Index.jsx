import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';

export default function InstallIndex({ requirements }) {
    const { data, setData, post, processing, errors } = useForm({
        license_key: 'DUMMY-LICENSE-KEY',
        db_host: '127.0.0.1',
        db_port: '3306',
        db_database: 'hiddenleaf',
        db_username: 'root',
        db_password: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/install');
    };

    const allExtensionsOk = Object.values(requirements.extensions).every(val => val);
    const canInstall = requirements.php && allExtensionsOk;

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    System Installation
                </h2>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                    
                    <div className="mb-6 border-b pb-4">
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Requirements</h3>
                        <ul className="text-sm space-y-1">
                            <li className="flex justify-between">
                                <span>PHP &gt;= 8.2</span>
                                <span className={requirements.php ? 'text-green-600' : 'text-red-600'}>
                                    {requirements.php ? 'OK' : 'Missing'}
                                </span>
                            </li>
                            {Object.entries(requirements.extensions).map(([ext, loaded]) => (
                                <li key={ext} className="flex justify-between">
                                    <span>{ext}</span>
                                    <span className={loaded ? 'text-green-600' : 'text-red-600'}>
                                        {loaded ? 'OK' : 'Missing'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {!canInstall ? (
                        <div className="text-red-600 text-sm font-medium">
                            Please resolve the missing requirements before proceeding.
                        </div>
                    ) : (
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">License Key</label>
                                <input type="text" value={data.license_key} onChange={e => setData('license_key', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                {errors.license_key && <p className="text-red-500 text-xs mt-1">{errors.license_key}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">DB Host</label>
                                    <input type="text" value={data.db_host} onChange={e => setData('db_host', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                    {errors.db_host && <p className="text-red-500 text-xs mt-1">{errors.db_host}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">DB Port</label>
                                    <input type="text" value={data.db_port} onChange={e => setData('db_port', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                    {errors.db_port && <p className="text-red-500 text-xs mt-1">{errors.db_port}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">DB Name</label>
                                <input type="text" value={data.db_database} onChange={e => setData('db_database', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                {errors.db_database && <p className="text-red-500 text-xs mt-1">{errors.db_database}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">DB User</label>
                                    <input type="text" value={data.db_username} onChange={e => setData('db_username', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                    {errors.db_username && <p className="text-red-500 text-xs mt-1">{errors.db_username}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">DB Password</label>
                                    <input type="password" value={data.db_password} onChange={e => setData('db_password', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                </div>
                            </div>

                            <div>
                                <button type="submit" disabled={processing} className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                                    {processing ? 'Installing...' : 'Install Now'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}
