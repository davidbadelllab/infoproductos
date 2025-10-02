export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <img 
                    src="/apple-touch-icon.png" 
                    alt="HunterProducts" 
                    className="size-5 rounded-sm"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    HunterProducts
                </span>
            </div>
        </>
    );
}
