"use client"

import { useRouter, useSearchParams } from "next/navigation"

import { cn } from "@/lib/utils"
import { login } from "@/lib/api"
import { loginSchema, type LoginFormValues } from "@/lib/schemas/login"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Field, FieldDescription, FieldSeparator } from "@/components/ui/field"
import { AppleIcon, GoogleIcon } from "@/components/social-icons"
import { LoginSidePanel } from "@/components/login-side-panel"
import { ConfigForm } from "@/components/config-form/config-form"
import type { FieldConfig } from "@/lib/forms/types"
import { REDIRECT_PARAM, sanitizeRedirectTarget } from "@/lib/navigation/safe-redirect"

/**
 * KOZ-18: rebuilt on the generic, config-driven `<ConfigForm>` from KOZ-17 —
 * the field config + `loginSchema` below is the single source of truth for
 * shape/validation, and `login` (lib/api.ts) is passed directly as the
 * `<ConfigForm>` `action` since its `LoginResult` shape is already
 * structurally an `ActionResult`. A failed login returns a generic
 * `message` (no `fieldErrors` — the backend intentionally doesn't
 * distinguish "unknown email" from "wrong password"), which `<ConfigForm>`
 * surfaces as the shared banner through the same field-wrapper mechanism
 * used for field-level errors.
 *
 * The header/social-buttons/separator (KOZ-16) sit outside the `<form>`
 * `<ConfigForm>` renders internally — they're disabled/non-interactive
 * decoration, not form controls, so this doesn't change submit behavior.
 *
 * KOZ-20: on success, navigates to whatever page proxy.ts's route guard
 * originally bounced the visitor away from (carried as `?redirect=`),
 * falling back to `/` (KOZ-21) when there is none or it fails validation —
 * see lib/navigation/safe-redirect.ts for the open-redirect guard.
 */
const fields: FieldConfig<LoginFormValues>[] = [
  {
    name: "email",
    label: "E-mailadres",
    type: "email",
    placeholder: "naam@bedrijf.nl",
    autoComplete: "email",
  },
  {
    name: "password",
    label: "Wachtwoord",
    type: "password",
    autoComplete: "current-password",
  },
]

const defaultValues: LoginFormValues = { email: "", password: "" }

export function LoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()
  const searchParams = useSearchParams()
  const redirectTarget = sanitizeRedirectTarget(searchParams.get(REDIRECT_PARAM))

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <div className="flex flex-col gap-6 p-6 md:p-8">
            <div className="flex flex-col items-center gap-2 text-center">
              <h1 className="text-2xl font-bold">Welkom terug</h1>
              <p className="text-balance text-muted-foreground">
                Log in op je Kozijnr-account
              </p>
            </div>
            {/*
              KOZ-16: social login bovenaan. Beide knoppen zijn disabled —
              er bestaat nog geen Apple- of Google-login-koppeling in de
              backend, dit is puur de visuele plek voor latere
              implementatie (Google expliciet vereist door het ticket).
            */}
            <Field className="grid grid-cols-2 gap-4">
              <Button variant="outline" type="button" disabled>
                <AppleIcon />
                Apple
              </Button>
              <Button variant="outline" type="button" disabled>
                <GoogleIcon />
                Google
              </Button>
            </Field>
            <FieldSeparator className="*:data-[slot=field-separator-content]:bg-card">
              Of ga verder met
            </FieldSeparator>
            <ConfigForm
              fields={fields}
              schema={loginSchema}
              defaultValues={defaultValues}
              action={login}
              onSuccess={() => router.push(redirectTarget)}
              submitLabel="Inloggen"
              pendingLabel="Bezig met inloggen..."
            />
            <FieldDescription className="text-center">
              <a href="#">Wachtwoord vergeten?</a>
            </FieldDescription>
          </div>
          <LoginSidePanel />
        </CardContent>
      </Card>
    </div>
  )
}
