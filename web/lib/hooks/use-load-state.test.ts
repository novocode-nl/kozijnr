// @vitest-environment jsdom
import { describe, expect, it } from "vitest"
import { act, renderHook, waitFor } from "@testing-library/react"

import { useLoadState } from "./use-load-state"

describe("useLoadState", () => {
  it("goes loading -> loaded with the fetched data", async () => {
    const { result } = renderHook(() => useLoadState(() => Promise.resolve(["a"]), []))
    expect(result.current[0]).toEqual({ status: "loading" })
    await waitFor(() => expect(result.current[0]).toEqual({ status: "loaded", data: ["a"] }))
  })

  it("goes to error when the loader rejects", async () => {
    const { result } = renderHook(() => useLoadState(() => Promise.reject(new Error("x")), []))
    await waitFor(() => expect(result.current[0]).toEqual({ status: "error" }))
  })

  it("ignores a stale resolution that lands after the deps changed", async () => {
    // De race die de cancelled-guard echt afdekt: de eerste loader resolvet
    // pas NADAT de tweede al geladen is — zonder guard overschrijft het
    // verouderde resultaat de verse state en faalt deze test aantoonbaar.
    const resolvers: Array<(v: string[]) => void> = []
    const load = () => new Promise<string[]>((resolve) => resolvers.push(resolve))
    const { result, rerender } = renderHook(({ dep }) => useLoadState(load, [dep]), {
      initialProps: { dep: 1 },
    })

    rerender({ dep: 2 })
    await act(async () => {
      resolvers[1](["fresh"])
      await Promise.resolve()
    })
    await waitFor(() => expect(result.current[0]).toEqual({ status: "loaded", data: ["fresh"] }))

    await act(async () => {
      resolvers[0](["stale"])
      await Promise.resolve()
    })
    expect(result.current[0]).toEqual({ status: "loaded", data: ["fresh"] })
  })

  it("exposes the raw setter for optimistic updates", async () => {
    const { result } = renderHook(() => useLoadState(() => Promise.resolve(["a"]), []))
    await waitFor(() => expect(result.current[0].status).toBe("loaded"))
    act(() => {
      result.current[1]((current) =>
        current.status === "loaded" ? { status: "loaded", data: ["b", ...current.data] } : current
      )
    })
    expect(result.current[0]).toEqual({ status: "loaded", data: ["b", "a"] })
  })
})
