"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"

import { cn } from "@/lib/utils"
import { adminLogin } from "@/lib/api"
import { loginSchema, type LoginFormValues } from "@/lib/schemas/login"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"

/**
 * Super-admin counterpart to components/login-form.tsx (KOZ-14 rework,
 * round 5). Same react-hook-form + Zod + shadcn pattern, same
 * `loginSchema` (both realms authenticate with a plain email/password
 * pair — see config/packages/security.yaml's `super_admin` firewall's
 * `json_login` config, which uses the same `username_path`/`password_path`
 * shape as the tenant realm) — only the submit handler differs, posting to
 * POST /api/admin/login (lib/api.ts's `adminLogin`) instead of
 * POST /api/login.
 *
 * Rendered by app/login/page.tsx when the Host resolves to the admin
 * context (lib/context/app-context.ts) — /login is the single path for
 * both forms, this component is never routed to directly.
 */
export function AdminLoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
  })

  async function onSubmit(values: LoginFormValues) {
    setFormError(null)
    setIsSubmitting(true)

    const result = await adminLogin(values)

    setIsSubmitting(false)

    if (!result.success) {
      setFormError(result.message)
      return
    }

    // The backend sets the super-admin session as a Set-Cookie, forwarded
    // by app/api/admin/login/route.ts — nothing left for client-side JS to
    // store. /dashboard is the same shared path the tenant form redirects
    // to; the Host context alone decides which content renders there.
    router.push("/dashboard")
  }

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="p-0">
          <form
            className="p-6 md:p-8"
            onSubmit={handleSubmit(onSubmit)}
            noValidate
          >
            <FieldGroup>
              <div className="flex flex-col items-center gap-2 text-center">
                <h1 className="text-2xl font-bold">Kozijnr Admin</h1>
                <p className="text-balance text-muted-foreground">
                  Log in als beheerder
                </p>
              </div>
              <Field data-invalid={!!errors.email}>
                <FieldLabel htmlFor="email">E-mailadres</FieldLabel>
                <Input
                  id="email"
                  type="email"
                  placeholder="naam@kozijnr.nl"
                  autoComplete="email"
                  aria-invalid={!!errors.email}
                  {...register("email")}
                />
                <FieldError errors={[errors.email]} />
              </Field>
              <Field data-invalid={!!errors.password}>
                <FieldLabel htmlFor="password">Wachtwoord</FieldLabel>
                <Input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  aria-invalid={!!errors.password}
                  {...register("password")}
                />
                <FieldError errors={[errors.password]} />
              </Field>
              {formError && (
                <div
                  role="alert"
                  className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                  {formError}
                </div>
              )}
              <Field>
                <Button type="submit" disabled={isSubmitting}>
                  {isSubmitting ? "Bezig met inloggen..." : "Inloggen"}
                </Button>
              </Field>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
