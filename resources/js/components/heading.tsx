export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-heading02 font-bold'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="mt-1 text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
