import { useEffect } from 'react'
import { router } from '@inertiajs/react'

const isInternalLink = (href) => {
    if (!href || href.startsWith('#')) {
        return false
    }

    if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(href)) {
        return false
    }

    if (href.startsWith('//')) {
        return false
    }

    try {
        const url = new URL(href, window.location.href)
        return url.origin === window.location.origin
    } catch {
        return false
    }
}

const normalizeHref = (href) => {
    try {
        const url = new URL(href, window.location.href)
        return `${url.pathname}${url.search}`
    } catch {
        return href
    }
}

const getLinkElement = (target) => {
    while (target && target !== document.documentElement) {
        if (target instanceof HTMLElement && target.tagName === 'A' && target.hasAttribute('href')) {
            return target
        }
        target = target.parentElement
    }
    return null
}

export default function useInertiaPrefetch(urls = [], options = { global: false }) {
    useEffect(() => {
        const prefetchHref = (href) => {
            if (!href || !isInternalLink(href)) {
                return
            }

            const path = normalizeHref(href)
            if (path === `${window.location.pathname}${window.location.search}`) {
                return
            }

            router.prefetch(path)
        }

        const prefetchUrls = () => {
            urls.forEach((url) => {
                if (typeof url === 'string' && url.length > 0) {
                    prefetchHref(url)
                }
            })
        }

        const handlePrefetchEvent = (event) => {
            const anchor = getLinkElement(event.target)
            if (!anchor) {
                return
            }

            prefetchHref(anchor.getAttribute('href'))
        }

        const prefetchVisibleLinks = () => {
            const anchors = Array.from(document.querySelectorAll('a[href]'))
                .filter((link) => isInternalLink(link.getAttribute('href')))
                .slice(0, 25)

            anchors.forEach((anchor) => prefetchHref(anchor.getAttribute('href')))
        }

        let idleHandle
        if (options.global) {
            document.addEventListener('pointerenter', handlePrefetchEvent, { capture: true, passive: true })
            document.addEventListener('focusin', handlePrefetchEvent, { capture: true })
            document.addEventListener('touchstart', handlePrefetchEvent, { capture: true, passive: true })

            idleHandle = window.requestIdleCallback
                ? window.requestIdleCallback(prefetchVisibleLinks)
                : window.setTimeout(prefetchVisibleLinks, 250)
        } else if (urls && urls.length > 0) {
            idleHandle = window.requestIdleCallback
                ? window.requestIdleCallback(prefetchUrls)
                : window.setTimeout(prefetchUrls, 250)
        }

        return () => {
            if (options.global) {
                document.removeEventListener('pointerenter', handlePrefetchEvent, { capture: true })
                document.removeEventListener('focusin', handlePrefetchEvent, { capture: true })
                document.removeEventListener('touchstart', handlePrefetchEvent, { capture: true })
            }

            if (idleHandle != null) {
                if (window.cancelIdleCallback) {
                    window.cancelIdleCallback(idleHandle)
                } else {
                    window.clearTimeout(idleHandle)
                }
            }
        }
    }, [urls.join(','), options.global])
}
