import AppLayout from '@/Layouts/AppLayout';

export default function Dashboard({ user }) {
    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-12">
                <div className="max-w-4xl mx-auto">
                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h1 className="text-3xl font-bold text-white mb-4">
                            Welcome, {user.name}!
                        </h1>
                        <div className="text-gray-300 space-y-2">
                            <p>í³§ Email: {user.email}</p>
                            <p>í³± Phone: {user.phone}</p>
                            <p>í¶” Employee ID: {user.employee_id}</p>
                        </div>
                        
                        <div className="mt-8">
                            <form method="POST" action="/logout">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')} />
                                <button 
                                    type="submit"
                                    className="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-colors"
                                >
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
