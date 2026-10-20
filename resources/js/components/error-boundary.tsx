import { Component, type ErrorInfo, type ReactNode } from 'react';

/**
 * Shows what went wrong instead of a blank page, with a way back. The message stays in English:
 * it is for whoever reports the problem, and the translations may be what failed.
 */
export class ErrorBoundary extends Component<{ children: ReactNode; resetKey?: unknown }, { error: Error | null }> {
    state: { error: Error | null } = { error: null };

    static getDerivedStateFromError(error: Error) {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error(error, info.componentStack);
    }

    componentDidUpdate(previous: { resetKey?: unknown }) {
        // Going to another page clears the error.
        if (this.state.error && previous.resetKey !== this.props.resetKey) this.setState({ error: null });
    }

    render() {
        if (!this.state.error) return this.props.children;

        return (
            <div className="flex min-h-screen items-center justify-center bg-[#f0f2f5] p-4 text-slate-900 dark:bg-neutral-950 dark:text-neutral-100">
                <div className="w-full max-w-md space-y-3 rounded-lg bg-white p-6 text-center shadow-sm dark:bg-neutral-900">
                    <p className="text-lg font-semibold">Something went wrong on this page.</p>
                    <p className="text-muted-foreground font-mono text-xs break-words">{this.state.error.message}</p>
                    <div className="flex justify-center gap-2">
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="bg-brand rounded-md px-4 py-2 text-sm font-semibold text-white"
                        >
                            Reload
                        </button>
                        <a href="/feed" className="rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold dark:bg-neutral-800">
                            Home
                        </a>
                    </div>
                </div>
            </div>
        );
    }
}
