import { Notice } from '@/components/notice';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <Notice tone="error" title={title || 'Something went wrong.'}>
            <ul className="list-inside list-disc">
                {Array.from(new Set(errors)).map((error, index) => (
                    <li key={index}>{error}</li>
                ))}
            </ul>
        </Notice>
    );
}
