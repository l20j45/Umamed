import { HTMLAttributes } from 'react';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p {...props} className={`mt-1.5 text-[12px] text-arcilla-600 ${className}`}>
            {message}
        </p>
    ) : null;
}
