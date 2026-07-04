export type CropTransform = {
    scale: number;
    x: number;
    y: number;
};

export type SourceRect = {
    sx: number;
    sy: number;
    sw: number;
    sh: number;
};

const MAX_ZOOM = 8;

export const coverScale = (naturalWidth: number, naturalHeight: number, viewport: number): number => {
    if (naturalWidth <= 0 || naturalHeight <= 0) {
        return 1;
    }

    return Math.max(viewport / naturalWidth, viewport / naturalHeight);
};

export const clampTransform = (
    transform: CropTransform,
    naturalWidth: number,
    naturalHeight: number,
    viewport: number,
): CropTransform => {
    const scale = Math.max(transform.scale, coverScale(naturalWidth, naturalHeight, viewport));
    const displayWidth = naturalWidth * scale;
    const displayHeight = naturalHeight * scale;

    const x = Math.min(0, Math.max(viewport - displayWidth, transform.x));
    const y = Math.min(0, Math.max(viewport - displayHeight, transform.y));

    return { scale, x, y };
};

export const centerTransform = (naturalWidth: number, naturalHeight: number, viewport: number): CropTransform => {
    const scale = coverScale(naturalWidth, naturalHeight, viewport);

    return {
        scale,
        x: (viewport - naturalWidth * scale) / 2,
        y: (viewport - naturalHeight * scale) / 2,
    };
};

export const zoomTransform = (
    transform: CropTransform,
    factor: number,
    naturalWidth: number,
    naturalHeight: number,
    viewport: number,
): CropTransform => {
    const minScale = coverScale(naturalWidth, naturalHeight, viewport);
    const nextScale = Math.min(minScale * MAX_ZOOM, Math.max(transform.scale * factor, minScale));
    const center = viewport / 2;
    const sourceX = (center - transform.x) / transform.scale;
    const sourceY = (center - transform.y) / transform.scale;

    return clampTransform(
        {
            scale: nextScale,
            x: center - sourceX * nextScale,
            y: center - sourceY * nextScale,
        },
        naturalWidth,
        naturalHeight,
        viewport,
    );
};

export const viewportToSource = (transform: CropTransform, viewport: number): SourceRect => {
    const size = viewport / transform.scale;

    return {
        sx: -transform.x / transform.scale,
        sy: -transform.y / transform.scale,
        sw: size,
        sh: size,
    };
};
